import { router } from '@inertiajs/react';
import { type FormEvent, useEffect, useState } from 'react';
import { firstError, formatMoney, fxStartOverUrl } from './admin';
import { formatDuration, startCountdown, startPolling } from './format';
import { Chevron, Notice } from './icons';
import type { FlutterwaveErrors, FlutterwaveFxPanel, FlutterwaveFxQuote } from './types';

interface Props {
    /** From fx()->panel($request->query('fx_quote'), $request->query('fx_trade')) on the server. */
    panel: FlutterwaveFxPanel;
    /** The prop name you passed the panel as, so checking for updates only reloads it. Defaults to all props. */
    reloadOnly?: string[];
    /** How often to check a pending quote or trade, in milliseconds. */
    pollInterval?: number;
    /** Added to the wrapper. */
    className?: string;
}


export function FxConverter({ panel, reloadOnly, pollInterval = 3000, className }: Props) {
    const { quote, trade } = panel;
    const waiting = Boolean(trade?.pending || (!trade && quote?.pending));

    useEffect(() => {
        if (!waiting || !pollInterval) {
            return;
        }

        return startPolling(() => router.reload(reloadOnly ? { only: reloadOnly } : {}), pollInterval);
    }, [waiting, pollInterval, reloadOnly?.join(',')]);

    const startOver = () => router.visit(fxStartOverUrl());

    return (
        <div className={`flw flw-card flw-card--narrow${className ? ` ${className}` : ''}`}>
            {panel.error && <Notice text={panel.error} ok={false} />}
            {trade ? (
                <TradeResult panel={panel} onStartOver={startOver} />
            ) : quote ? (
                <QuoteStep panel={panel} quote={quote} onStartOver={startOver} />
            ) : (
                <QuoteRequest panel={panel} />
            )}
        </div>
    );
}

function QuoteRequest({ panel }: { panel: FlutterwaveFxPanel }) {
    const [pair, setPair] = useState(panel.pairs[0]?.join('/') ?? 'NGN/USD');
    const [quantity, setQuantity] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [base, target] = pair.split('/');

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!(Number(quantity) > 0)) {
            setError(`Enter how much ${base} to sell.`);

            return;
        }

        setBusy(true);
        setError(null);
        router.post(panel.urls!.quote, { base, target, quantity }, {
            preserveScroll: true,
            onError: (errors) => setError(firstError(errors as FlutterwaveErrors)),
            onFinish: () => setBusy(false),
        });
    };

    if (!panel.urls) {
        return <p className="flw-text">Add Route::flutterwaveAdmin() to your routes to request quotes here.</p>;
    }

    return (
        <form onSubmit={submit} className="flw-stack" style={{ gap: 16 }}>
            <div className="flw-stack">
                <h3 className="flw-title">Convert currency</h3>
                <p className="flw-text">Get a rate, then trade it within 5 minutes. Weekdays only, from USD 1,000 or its equivalent.</p>
            </div>
            <hr className="flw-rule" />
            <label className="flw-field">
                <span className="flw-field__label">Convert</span>
                <span className="flw-select">
                    <select value={pair} onChange={(event) => setPair(event.target.value)} className="flw-input">
                        {panel.pairs.map(([from, to]) => (
                            <option key={`${from}/${to}`} value={`${from}/${to}`}>{from} to {to}</option>
                        ))}
                    </select>
                    <Chevron />
                </span>
            </label>
            <label className={`flw-field${error ? ' has-error' : ''}`}>
                <span className="flw-field__label">Amount of {base} to sell</span>
                <input inputMode="decimal" value={quantity} onChange={(event) => setQuantity(event.target.value.replace(/[^\d.]/g, ''))} placeholder="2000000" className="flw-input flw-num" />
                {error && <span role="alert" className="flw-field__error">{error}</span>}
            </label>
            <button type="submit" disabled={busy} className="flw-btn flw-btn--primary flw-btn--block">
                {busy && <span className="flw-btn__spinner" aria-hidden="true" />}
                {busy ? 'Requesting' : `Get a ${base} to ${target} rate`}
            </button>
        </form>
    );
}

function QuoteStep({ panel, quote, onStartOver }: { panel: FlutterwaveFxPanel; quote: FlutterwaveFxQuote; onStartOver: () => void }) {
    const [secondsLeft, setSecondsLeft] = useState<number | null>(quote.expires_in);
    const [narration, setNarration] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (quote.expires_in === null) {
            setSecondsLeft(null);

            return;
        }

        return startCountdown(quote.expires_in, setSecondsLeft);
    }, [quote.id, quote.expires_in]);

    if (quote.pending) {
        return (
            <div className="flw-stack" style={{ gap: 10 }} aria-live="polite">
                <h3 className="flw-title">Getting a rate</h3>
                <hr className="flw-rule" />
                <p className="flw-countdown"><span className="flw-ring" aria-hidden="true" />Flutterwave usually prices a quote within a few seconds. This updates by itself.</p>
            </div>
        );
    }

    const expired = quote.status === 'EXPIRED' || (quote.status === 'READY' && secondsLeft === 0);

    if (!quote.tradable || expired) {
        return (
            <div className="flw-stack" style={{ gap: 14 }} aria-live="polite">
                <h3 className="flw-title">{expired ? 'This rate has expired' : 'No rate available'}</h3>
                <hr className="flw-rule" />
                <p className="flw-text">{expired ? 'Rates are held for 5 minutes. Get a new one to continue.' : quote.message}</p>
                <button type="button" onClick={onStartOver} className="flw-btn flw-btn--primary flw-btn--block">Get a new rate</button>
            </div>
        );
    }

    const sell = formatMoney(quote.base, quote.approved_quantity);
    const get = formatMoney(quote.target, quote.total_value);
    const trimmed = quote.quantity !== null && quote.approved_quantity !== null && quote.approved_quantity < quote.quantity;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!narration.trim()) {
            setError('Add a note for your records, e.g. Supplier payment.');

            return;
        }

        if (!confirming) {
            setError(null);
            setConfirming(true);

            return;
        }

        setBusy(true);
        router.post(panel.urls!.trade, { quote_id: quote.id, narration }, {
            preserveScroll: true,
            onError: (errors) => {
                setError(firstError(errors as FlutterwaveErrors));
                setConfirming(false);
            },
            onFinish: () => setBusy(false),
        });
    };

    return (
        <form onSubmit={submit} className="flw-stack" style={{ gap: 16 }}>
            <div className="flw-row">
                <h3 className="flw-title">Your rate</h3>
                {secondsLeft !== null && <p className="flw-countdown">Held for <strong>{formatDuration(secondsLeft)}</strong></p>}
            </div>
            <hr className="flw-rule" />
            <dl className="flw-dl">
                <div><dt>You sell</dt><dd>{sell}</dd></div>
                <div><dt>You get</dt><dd>{get}</dd></div>
                <div><dt>Rate</dt><dd>1 {quote.base} = {quote.rate} {quote.target}</dd></div>
            </dl>
            {trimmed && <p className="flw-notice flw-notice--warning">Flutterwave approved {sell} of the {formatMoney(quote.base, quote.quantity)} you asked for.</p>}
            {panel.urls && (
                <>
                    <label className={`flw-field${error ? ' has-error' : ''}`}>
                        <span className="flw-field__label">Note for your records</span>
                        <input value={narration} onChange={(event) => { setNarration(event.target.value); setConfirming(false); }} placeholder="Supplier payment" className="flw-input" />
                        {error && <span role="alert" className="flw-field__error">{error}</span>}
                    </label>
                    {confirming && <p className="flw-notice flw-notice--info">Sell {sell} for {get}? Trades can’t be undone.</p>}
                    <button type="submit" disabled={busy} className="flw-btn flw-btn--primary flw-btn--block">
                        {busy && <span className="flw-btn__spinner" aria-hidden="true" />}
                        {busy ? 'Trading' : confirming ? 'Confirm trade' : 'Trade at this rate'}
                    </button>
                    <button type="button" onClick={confirming ? () => setConfirming(false) : onStartOver} className="flw-link-btn flw-link-btn--quiet">{confirming ? 'Back' : 'Get a different rate'}</button>
                </>
            )}
        </form>
    );
}

function TradeResult({ panel, onStartOver }: { panel: FlutterwaveFxPanel; onStartOver: () => void }) {
    const trade = panel.trade!;
    const settled = trade.status === 'SETTLED';
    const failed = trade.status === 'FAILED';

    return (
        <div className="flw-stack" style={{ gap: 14 }} aria-live="polite">
            <h3 className="flw-title">{settled ? 'Trade settled' : failed ? 'Trade failed' : 'Trade in progress'}</h3>
            <hr className="flw-rule" />
            {settled ? (
                <p className="flw-text">Sold {formatMoney(trade.base, trade.sold)} for <strong>{formatMoney(trade.target, trade.bought)}</strong>{trade.recipient ? `, paid to ${trade.recipient}` : ''}.</p>
            ) : failed ? (
                <p className="flw-notice flw-notice--error">{trade.message ?? 'Flutterwave couldn’t complete the trade.'}</p>
            ) : (
                <p className="flw-countdown"><span className="flw-ring" aria-hidden="true" />Selling {formatMoney(trade.base, trade.sold)} for {formatMoney(trade.target, trade.bought)}. This updates by itself.</p>
            )}
            <p className="flw-muted">Reference {trade.reference ?? trade.id}</p>
            {!trade.pending && <button type="button" onClick={onStartOver} className="flw-btn flw-btn--primary flw-btn--block">Start another conversion</button>}
        </div>
    );
}
