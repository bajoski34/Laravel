import { router } from '@inertiajs/react';
import { type FormEvent, useEffect, useState } from 'react';
import { firstError, formatMoney, fxStartOverUrl } from './admin';
import { formatDuration, startCountdown, startPolling } from './format';
import type { FlutterwaveErrors, FlutterwaveFxPanel, FlutterwaveFxQuote } from './types';

interface Props {
    /** From fx()->panel($request->query('fx_quote'), $request->query('fx_trade')) on the server. */
    panel: FlutterwaveFxPanel;
    /** The prop name you passed the panel as, so checking for updates only reloads it. Defaults to all props. */
    reloadOnly?: string[];
    /** How often to check a pending quote or trade, in milliseconds. */
    pollInterval?: number;
    /** Replaces the default classes. */
    className?: string;
}

const card = 'grid max-w-md gap-4 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800';
const primary = 'rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700 disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200';
const quiet = 'text-sm text-neutral-600 underline-offset-2 hover:underline dark:text-neutral-300';

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
        <div className={className ?? card}>
            {panel.error && <p role="alert" className="text-sm text-red-700 dark:text-red-400">{panel.error}</p>}
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
        return <p className="text-sm text-neutral-500 dark:text-neutral-400">Add Route::flutterwaveAdmin() to your routes to request quotes here.</p>;
    }

    return (
        <form onSubmit={submit} className="grid gap-4">
            <div className="grid gap-1">
                <h3 className="text-base font-semibold">Convert currency</h3>
                <p className="text-sm text-neutral-500 dark:text-neutral-400">Get a rate, then trade it within 5 minutes. Weekdays only, from USD 1,000 or its equivalent.</p>
            </div>
            <div className="grid gap-3 sm:grid-cols-[auto_1fr]">
                <label className="grid gap-1 text-sm">
                    <span className="font-medium">Convert</span>
                    <select value={pair} onChange={(event) => setPair(event.target.value)} className="rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-950">
                        {panel.pairs.map(([from, to]) => (
                            <option key={`${from}/${to}`} value={`${from}/${to}`}>{from} to {to}</option>
                        ))}
                    </select>
                </label>
                <label className="grid gap-1 text-sm">
                    <span className="font-medium">Amount of {base} to sell</span>
                    <input inputMode="decimal" value={quantity} onChange={(event) => setQuantity(event.target.value.replace(/[^\d.]/g, ''))} placeholder="2000000" className="rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm tabular-nums dark:border-neutral-700 dark:bg-neutral-950" />
                </label>
            </div>
            {error && <p role="alert" className="text-sm text-red-700 dark:text-red-400">{error}</p>}
            <button type="submit" disabled={busy} className={`${primary} w-fit`}>{busy ? 'Requesting…' : `Get a ${base} to ${target} rate`}</button>
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
            <div className="grid gap-2" aria-live="polite">
                <h3 className="text-base font-semibold">Getting a rate…</h3>
                <p className="text-sm text-neutral-500 dark:text-neutral-400">Flutterwave usually prices a quote within a few seconds. This updates by itself.</p>
            </div>
        );
    }

    const expired = quote.status === 'EXPIRED' || (quote.status === 'READY' && secondsLeft === 0);

    if (!quote.tradable || expired) {
        return (
            <div className="grid gap-3" aria-live="polite">
                <h3 className="text-base font-semibold">{expired ? 'This rate has expired' : 'No rate available'}</h3>
                <p className="text-sm text-neutral-600 dark:text-neutral-300">{expired ? 'Rates are held for 5 minutes. Get a new one to continue.' : quote.message}</p>
                <button type="button" onClick={onStartOver} className={`${primary} w-fit`}>Get a new rate</button>
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
        <form onSubmit={submit} className="grid gap-4">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="text-base font-semibold">Your rate</h3>
                {secondsLeft !== null && <span className="text-sm tabular-nums text-neutral-500 dark:text-neutral-400">Held for {formatDuration(secondsLeft)}</span>}
            </div>
            <dl className="grid grid-cols-2 gap-3">
                <div><dt className="text-xs text-neutral-500 dark:text-neutral-400">You sell</dt><dd className="font-semibold tabular-nums">{sell}</dd></div>
                <div><dt className="text-xs text-neutral-500 dark:text-neutral-400">You get</dt><dd className="font-semibold tabular-nums">{get}</dd></div>
                <div className="col-span-2"><dt className="text-xs text-neutral-500 dark:text-neutral-400">Rate</dt><dd className="tabular-nums">1 {quote.base} = {quote.rate} {quote.target}</dd></div>
            </dl>
            {trimmed && <p className="rounded-md bg-amber-50 p-2 text-sm text-amber-900 dark:bg-amber-400/10 dark:text-amber-300">Flutterwave approved {sell} of the {formatMoney(quote.base, quote.quantity)} you asked for.</p>}
            {panel.urls && (
                <>
                    <label className="grid gap-1 text-sm">
                        <span className="font-medium">Note for your records</span>
                        <input value={narration} onChange={(event) => { setNarration(event.target.value); setConfirming(false); }} placeholder="Supplier payment" className="rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-950" />
                    </label>
                    {error && <p role="alert" className="text-sm text-red-700 dark:text-red-400">{error}</p>}
                    {confirming && <p className="text-sm font-medium">Sell {sell} for {get}? Trades can’t be undone.</p>}
                    <div className="flex flex-wrap items-center gap-3">
                        <button type="submit" disabled={busy} className={primary}>{busy ? 'Trading…' : confirming ? 'Confirm trade' : 'Trade at this rate'}</button>
                        <button type="button" onClick={confirming ? () => setConfirming(false) : onStartOver} className={quiet}>{confirming ? 'Back' : 'Get a different rate'}</button>
                    </div>
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
        <div className="grid gap-3" aria-live="polite">
            <h3 className="text-base font-semibold">{settled ? 'Trade settled' : failed ? 'Trade failed' : 'Trade in progress…'}</h3>
            <p className={`text-sm ${failed ? 'text-red-700 dark:text-red-400' : 'text-neutral-600 dark:text-neutral-300'}`}>
                {settled
                    ? `Sold ${formatMoney(trade.base, trade.sold)} for ${formatMoney(trade.target, trade.bought)}${trade.recipient ? `, paid to ${trade.recipient}` : ''}.`
                    : failed
                      ? trade.message ?? 'Flutterwave couldn’t complete the trade.'
                      : `Selling ${formatMoney(trade.base, trade.sold)} for ${formatMoney(trade.target, trade.bought)}. This updates by itself.`}
            </p>
            <p className="text-xs text-neutral-500 dark:text-neutral-400">Reference {trade.reference ?? trade.id}</p>
            {!trade.pending && <button type="button" onClick={onStartOver} className={`${primary} w-fit`}>Start another conversion</button>}
        </div>
    );
}
