import { router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { formatDate, formatMoney, statusLabel, statusTone, urlWith } from './admin';
import { Pager } from './pager';
import type { FlutterwaveSettlementsPanel } from './types';

const amount = (value?: number) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 });

/** Settlements, from settlements()->panel($request->query()), with a breakdown of the one picked. */
export function Settlements({ panel, reloadOnly }: { panel: FlutterwaveSettlementsPanel; reloadOnly?: string[] }) {
    const [filters, setFilters] = useState(panel.filters);
    const visit = (params: Record<string, string | null>) => router.visit(urlWith(params), { preserveScroll: true, preserveState: true, ...(reloadOnly ? { only: reloadOnly } : {}) });
    const selected = panel.selected;

    const filter = (event: FormEvent) => {
        event.preventDefault();
        visit({ from: filters.from ?? '', to: filters.to ?? '', settlement: null });
    };

    return (
        <div className="flw">
            <form onSubmit={filter} className="flw-filters">
                <label className="flw-field"><span className="flw-field__label">From</span><input type="date" value={filters.from ?? ''} onChange={(event) => setFilters({ ...filters, from: event.target.value })} className="flw-input flw-input--sm" /></label>
                <label className="flw-field"><span className="flw-field__label">To</span><input type="date" value={filters.to ?? ''} onChange={(event) => setFilters({ ...filters, to: event.target.value })} className="flw-input flw-input--sm" /></label>
                <button type="submit" className="flw-btn flw-btn--primary flw-btn--sm">Show</button>
            </form>

            {panel.error ? (
                <p className="flw-notice flw-notice--error">Couldn’t load settlements: {panel.error}</p>
            ) : panel.items.length === 0 ? (
                <p className="flw-text">No settlements in this period.</p>
            ) : (
                <ul className="flw-rows">
                    {panel.items.map((settlement) => {
                        const open = selected?.id === settlement.id;

                        return (
                            <li key={settlement.id}>
                                <div className="flw-row">
                                    <div className="flw-stack">
                                        <span className="flw-amount">{formatMoney(settlement.currency ?? null, settlement.net_amount ?? null)}</span>
                                        <span className="flw-meta">
                                            <span>{settlement.transaction_count ?? 0} {settlement.transaction_count === 1 ? 'payment' : 'payments'} on {formatDate(settlement.transaction_date, false)}</span>
                                            <span>Due {formatDate(settlement.due_date, false)}</span>
                                            {settlement.processed_date && <span>Paid {formatDate(settlement.processed_date, false)}</span>}
                                        </span>
                                    </div>
                                    <span className="flw-pill" data-tone={statusTone(settlement.status)}>{statusLabel(settlement.status)}</span>
                                </div>
                                {settlement.flag_message && <p className="flw-notice flw-notice--warning">{settlement.flag_message}</p>}
                                <button type="button" onClick={() => visit({ settlement: open ? null : String(settlement.id) })} className="flw-link-btn">{open ? 'Hide details' : 'Details'}</button>
                                {open && selected && (
                                    <>
                                        <dl className="flw-dl">
                                            <div><dt>Collected</dt><dd>{formatMoney(selected.currency ?? null, selected.gross_amount ?? null)}</dd></div>
                                            <div><dt>Flutterwave fees</dt><dd>{formatMoney(selected.currency ?? null, (selected.app_fee ?? 0) + (selected.merchant_fee ?? 0) + (selected.stampduty_charge ?? 0))}</dd></div>
                                            <div><dt>Refunds and chargebacks</dt><dd>{formatMoney(selected.currency ?? null, (selected.refund ?? 0) + (selected.chargeback ?? 0))}</dd></div>
                                            <div><dt>Paid to you</dt><dd>{formatMoney(selected.currency ?? null, selected.net_amount ?? null)}</dd></div>
                                        </dl>
                                        {selected.transactions.length > 0 && (
                                            <div className="flw-table-wrap">
                                                <table className="flw-table">
                                                    <thead><tr><th>Reference</th><th>Customer</th><th>Charged</th><th>Fee</th><th>Settled</th></tr></thead>
                                                    <tbody>
                                                        {selected.transactions.map((transaction, index) => (
                                                            <tr key={transaction.id ?? index}>
                                                                <td>{transaction.tx_ref ?? transaction.id}</td>
                                                                <td>{transaction.customer_email}</td>
                                                                <td>{amount(transaction.charged_amount)}</td>
                                                                <td>{amount(transaction.app_fee)}</td>
                                                                <td className="flw-strong">{amount(transaction.settlement_amount)}</td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}

            <Pager page={panel.page} reloadOnly={reloadOnly} />
        </div>
    );
}
