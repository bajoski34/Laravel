import { router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { formatDate, statusLabel, statusTone, urlWith } from './admin';
import { Chevron } from './icons';
import { Pager } from './pager';
import type { FlutterwaveRefundsPanel } from './types';

/** Refunds, from refunds()->panel($request->query()). Start a refund from the Transactions component. */
export function Refunds({ panel, reloadOnly }: { panel: FlutterwaveRefundsPanel; reloadOnly?: string[] }) {
    const [filters, setFilters] = useState(panel.filters);
    const set = (key: string) => (event: { target: { value: string } }) => setFilters({ ...filters, [key]: event.target.value });

    const filter = (event: FormEvent) => {
        event.preventDefault();
        router.visit(urlWith({ from: filters.from ?? '', to: filters.to ?? '', status: filters.status ?? '' }), { preserveScroll: true, preserveState: true, ...(reloadOnly ? { only: reloadOnly } : {}) });
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
                            <option value="completed">Completed</option>
                            <option value="successful">Successful</option>
                            <option value="failed">Failed</option>
                        </select>
                        <Chevron />
                    </span>
                </label>
                <button type="submit" className="flw-btn flw-btn--primary flw-btn--sm">Show</button>
            </form>

            {panel.error ? (
                <p className="flw-notice flw-notice--error">Couldn’t load refunds: {panel.error}</p>
            ) : panel.items.length === 0 ? (
                <p className="flw-text">No refunds match these filters. Refund a payment from its transaction.</p>
            ) : (
                <div className="flw-table-wrap">
                    <table className="flw-table">
                        <thead><tr><th>Refunded</th><th>Status</th><th>Transaction</th><th>Reference</th><th>Reason</th><th>Date</th></tr></thead>
                        <tbody>
                            {panel.items.map((refund) => (
                                <tr key={refund.id}>
                                    <td className="flw-strong">{Number(refund.amount_refunded ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                    <td><span className="flw-pill" data-tone={statusTone(refund.status)}>{statusLabel(refund.status)}</span></td>
                                    <td>{refund.transaction_id}</td>
                                    <td>{refund.flw_ref}</td>
                                    <td>{refund.comment}</td>
                                    <td>{formatDate(refund.created_at)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Pager page={panel.page} reloadOnly={reloadOnly} />
        </div>
    );
}
