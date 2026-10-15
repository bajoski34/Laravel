import { router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { firstError, formatDate, formatMoney, statusLabel, statusTone, urlWith } from './admin';
import { Chevron, Notice } from './icons';
import { Pager } from './pager';
import type { FlutterwaveErrors, FlutterwaveTransactionsPanel } from './types';

type Transaction = FlutterwaveTransactionsPanel['items'][number];

interface Props {
    /** From transactions()->panel($request->query()) on the server. */
    panel: FlutterwaveTransactionsPanel;
    /** The prop name you passed the panel as, so filtering only reloads it. Defaults to all props. */
    reloadOnly?: string[];
}

export function Transactions({ panel, reloadOnly }: Props) {
    const [filters, setFilters] = useState(panel.filters);
    const [fee, setFee] = useState({ amount: '', currency: panel.items[0]?.currency ?? 'NGN', type: '' });
    const visit = (params: Record<string, string | null>) => router.visit(urlWith(params), { preserveScroll: true, preserveState: true, ...(reloadOnly ? { only: reloadOnly } : {}) });
    const set = (key: string) => (event: { target: { value: string } }) => setFilters({ ...filters, [key]: event.target.value });

    const filter = (event: FormEvent) => {
        event.preventDefault();
        visit({ from: filters.from ?? '', to: filters.to ?? '', status: filters.status ?? '', customer_email: filters.customer_email ?? '', tx_ref: filters.tx_ref ?? '', transaction: null });
    };

    const checkFee = (event: FormEvent) => {
        event.preventDefault();
        visit({ fee_amount: fee.amount, fee_currency: fee.currency, fee_type: fee.type });
    };

    return (
        <div className="flw">
            <form onSubmit={filter} className="flw-filters">
                <label className="flw-field"><span className="flw-field__label">From</span><input type="date" value={filters.from ?? ''} onChange={set('from')} className="flw-input flw-input--sm" /></label>
                <label className="flw-field"><span className="flw-field__label">To</span><input type="date" value={filters.to ?? ''} onChange={set('to')} className="flw-input flw-input--sm" /></label>
                <label className="flw-field">
                    <span className="flw-field__label">Status</span>
                    <span className="flw-select">
                        <select value={filters.status ?? ''} onChange={set('status')} className="flw-input flw-input--sm">
                            <option value="">Any</option>
                            <option value="successful">Successful</option>
                            <option value="failed">Failed</option>
                            <option value="pending">Pending</option>
                        </select>
                        <Chevron />
                    </span>
                </label>
                <label className="flw-field"><span className="flw-field__label">Customer email</span><input type="email" value={filters.customer_email ?? ''} onChange={set('customer_email')} placeholder="jane@example.com" className="flw-input flw-input--sm" /></label>
                <label className="flw-field"><span className="flw-field__label">Reference</span><input value={filters.tx_ref ?? ''} onChange={set('tx_ref')} placeholder="ORDER-42" className="flw-input flw-input--sm" /></label>
                <button type="submit" className="flw-btn flw-btn--primary flw-btn--sm">Show</button>
            </form>

            {panel.error ? (
                <p className="flw-notice flw-notice--error">Couldn’t load transactions: {panel.error}</p>
            ) : panel.items.length === 0 ? (
                <p className="flw-text">No transactions match these filters.</p>
            ) : (
                <ul className="flw-rows">
                    {panel.items.map((transaction) => (
                        <TransactionItem key={transaction.id} transaction={transaction} panel={panel} visit={visit} />
                    ))}
                </ul>
            )}

            <Pager page={panel.page} reloadOnly={reloadOnly} />

            <details className="flw-details">
                <summary>Check a fee</summary>
                <form onSubmit={checkFee} className="flw-filters">
                    <label className="flw-field"><span className="flw-field__label">Amount</span><input required inputMode="decimal" value={fee.amount} onChange={(event) => setFee({ ...fee, amount: event.target.value })} placeholder="10000" className="flw-input flw-input--sm" /></label>
                    <label className="flw-field"><span className="flw-field__label">Currency</span><input required maxLength={3} value={fee.currency} onChange={(event) => setFee({ ...fee, currency: event.target.value.toUpperCase() })} className="flw-input flw-input--sm" /></label>
                    <label className="flw-field">
                        <span className="flw-field__label">Paid by</span>
                        <span className="flw-select">
                            <select value={fee.type} onChange={(event) => setFee({ ...fee, type: event.target.value })} className="flw-input flw-input--sm">
                                <option value="">Any method</option>
                                <option value="card">Card</option>
                                <option value="debit_ng_account">Bank account</option>
                                <option value="mobilemoney">Mobile money</option>
                                <option value="bank_transfer">Bank transfer</option>
                            </select>
                            <Chevron />
                        </span>
                    </label>
                    <button type="submit" className="flw-btn flw-btn--outline flw-btn--sm">Check</button>
                </form>
                {panel.fee && (panel.fee.error ? (
                    <p className="flw-error-text">{panel.fee.error}</p>
                ) : (
                    <p className="flw-text">
                        Flutterwave’s fee is <strong>{formatMoney(panel.fee.currency ?? null, panel.fee.fee ?? null)}</strong>, so the customer is charged {formatMoney(panel.fee.currency ?? null, panel.fee.charge_amount ?? null)}
                        {panel.fee.stamp_duty_fee ? `, including ${formatMoney(panel.fee.currency ?? null, panel.fee.stamp_duty_fee)} stamp duty` : ''}.
                    </p>
                ))}
            </details>
        </div>
    );
}

function TransactionItem({ transaction, panel, visit }: { transaction: Transaction; panel: FlutterwaveTransactionsPanel; visit: (params: Record<string, string | null>) => void }) {
    const [refunding, setRefunding] = useState(false);
    const [amount, setAmount] = useState('');
    const [comment, setComment] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [message, setMessage] = useState<{ text: string; ok: boolean } | null>(null);
    const [busy, setBusy] = useState(false);
    const open = panel.timeline?.transaction_id === String(transaction.id);
    const total = formatMoney(transaction.currency, transaction.amount);

    const post = (url: string, data: Record<string, string>, success: string) => {
        setBusy(true);
        router.post(url, data, {
            preserveScroll: true,
            onSuccess: () => setMessage({ text: success, ok: true }),
            onError: (errors) => setMessage({ text: firstError(errors as FlutterwaveErrors), ok: false }),
            onFinish: () => setBusy(false),
        });
    };

    const refund = (event: FormEvent) => {
        event.preventDefault();

        if (!confirming) {
            setConfirming(true);

            return;
        }

        post(transaction.urls!.refund!, Object.fromEntries(Object.entries({ amount, comment }).filter(([, value]) => value !== '')), amount ? `Refund of ${formatMoney(transaction.currency, amount)} started.` : `Full refund of ${total} started.`);
        setRefunding(false);
        setConfirming(false);
    };

    return (
        <li>
            <div className="flw-row">
                <div className="flw-stack">
                    <span className="flw-amount">{total}</span>
                    <span className="flw-meta">
                        <span>{transaction.customer_name || transaction.customer_email}</span>
                        {transaction.customer_name && transaction.customer_email && <span>{transaction.customer_email}</span>}
                        <span>{transaction.tx_ref}</span>
                        <span>{formatDate(transaction.created_at)}</span>
                        {transaction.payment_type && <span>{transaction.payment_type.replace(/_/g, ' ')}</span>}
                    </span>
                </div>
                <span className="flw-pill" data-tone={statusTone(transaction.status)}>{statusLabel(transaction.status)}</span>
            </div>

            <div className="flw-actions">
                <button type="button" onClick={() => visit({ transaction: open ? null : String(transaction.id) })} className="flw-link-btn">{open ? 'Hide timeline' : 'Timeline'}</button>
                {transaction.urls?.refund && !refunding && <button type="button" onClick={() => setRefunding(true)} className="flw-btn flw-btn--outline flw-btn--sm">Refund</button>}
                {transaction.urls && <button type="button" disabled={busy} onClick={() => post(transaction.urls!.resend_webhook, {}, 'Webhook sent again.')} className="flw-btn flw-btn--outline flw-btn--sm">Resend webhook</button>}
            </div>

            {message && <Notice text={message.text} ok={message.ok} />}

            {refunding && (
                <form onSubmit={refund} className="flw-panel">
                    <label className="flw-field">
                        <span className="flw-field__label">Amount to refund</span>
                        <input inputMode="decimal" value={amount} onChange={(event) => { setAmount(event.target.value.replace(/[^\d.]/g, '')); setConfirming(false); }} placeholder={`Leave empty to refund ${total} in full`} className="flw-input" />
                    </label>
                    <label className="flw-field"><span className="flw-field__label">Reason</span><input maxLength={255} value={comment} onChange={(event) => setComment(event.target.value)} placeholder="Damaged on delivery" className="flw-input" /></label>
                    {confirming && <p className="flw-notice flw-notice--info">Refund {amount ? formatMoney(transaction.currency, amount) : `${total} in full`}? Refunds can’t be undone.</p>}
                    <div className="flw-actions">
                        <button type="submit" disabled={busy} className="flw-btn flw-btn--primary">{confirming ? 'Confirm refund' : 'Refund'}</button>
                        <button type="button" onClick={() => { setRefunding(false); setConfirming(false); }} className="flw-link-btn flw-link-btn--quiet">Cancel</button>
                    </div>
                </form>
            )}

            {open && panel.timeline && (panel.timeline.error ? (
                <p className="flw-error-text">{panel.timeline.error}</p>
            ) : (
                <ol className="flw-timeline">
                    {panel.timeline.events.map((event, index) => (
                        <li key={index}>{event.note ?? event.action} <span className="flw-muted">{formatDate(event.created_at)}</span></li>
                    ))}
                </ol>
            ))}
        </li>
    );
}
