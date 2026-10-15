import { router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { chargebackStatus, checkProof, firstError, formatDeadline } from './admin';
import { Notice } from './icons';
import type { FlutterwaveChargeback, FlutterwaveChargebackPanel, FlutterwaveErrors } from './types';

interface Props {
    /** From chargebacks()->panel() on the server. */
    panel: FlutterwaveChargebackPanel;
    /** Added to the wrapper. */
    className?: string;
}

export function Chargebacks({ panel, className }: Props) {
    return (
        <div className={`flw${className ? ` ${className}` : ''}`}>
            {panel.error ? (
                <p className="flw-notice flw-notice--error">Couldn’t load chargebacks: {panel.error}</p>
            ) : panel.items.length === 0 ? (
                <p className="flw-text">No chargebacks. When a customer disputes a payment with their bank, it shows up here.</p>
            ) : (
                <ul className="flw-rows">
                    {panel.items.map((chargeback) => (
                        <ChargebackItem key={chargeback.id} chargeback={chargeback} proofRules={panel.proof} />
                    ))}
                </ul>
            )}
            {panel.page && panel.page.total_pages > 1 && (
                <p className="flw-muted">Page {panel.page.current_page} of {panel.page.total_pages}, {panel.page.total} in total.</p>
            )}
        </div>
    );
}

function ChargebackItem({ chargeback, proofRules }: { chargeback: FlutterwaveChargeback; proofRules: FlutterwaveChargebackPanel['proof'] }) {
    const [mode, setMode] = useState<'idle' | 'accept' | 'decline'>('idle');
    const [comment, setComment] = useState('');
    const [proof, setProof] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [done, setDone] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const status = chargebackStatus(chargeback);
    const urgent = chargeback.respond_within !== null && chargeback.respond_within < 2 * 3600;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const urls = chargeback.urls!;
        const problem = mode === 'decline' ? checkProof(proof, proofRules) : null;

        if (!comment.trim() || problem) {
            setError(problem ?? (mode === 'accept' ? 'Say why you’re accepting it.' : 'Say what your evidence shows.'));

            return;
        }

        setBusy(true);
        setError(null);
        router.post(mode === 'accept' ? urls.accept : urls.decline, mode === 'accept' ? { comment } : { comment, proof }, {
            preserveScroll: true,
            forceFormData: mode === 'decline',
            onSuccess: () => {
                setDone(mode === 'accept' ? 'Accepted. The customer will be refunded.' : 'Dispute sent. Flutterwave emails you as it moves on.');
                setMode('idle');
            },
            onError: (errors) => setError(firstError(errors as FlutterwaveErrors)),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <li>
            <div className="flw-row">
                <div className="flw-stack">
                    <span className="flw-amount">{chargeback.amount?.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    <span className="flw-meta"><span>{chargeback.tx_ref}</span><span>{chargeback.flw_ref}</span></span>
                </div>
                <span className="flw-pill" data-tone={status.tone}>{status.label}</span>
            </div>

            {chargeback.respond_within !== null && (
                <p className={urgent ? 'flw-urgent' : 'flw-text'}>
                    {chargeback.respond_within > 0 ? `Respond within ${formatDeadline(chargeback.respond_within)}, or the customer is refunded automatically.` : 'The response deadline has passed.'}
                </p>
            )}

            {chargeback.comment && <p className="flw-text">Customer’s reason: {chargeback.comment}</p>}

            {done && <Notice text={done} ok />}

            {chargeback.urls && mode === 'idle' && !done && (
                <div className="flw-actions">
                    <button type="button" onClick={() => setMode('decline')} className="flw-btn flw-btn--primary flw-btn--sm">Dispute with proof</button>
                    <button type="button" onClick={() => setMode('accept')} className="flw-btn flw-btn--outline flw-btn--sm">Accept and refund</button>
                </div>
            )}

            {mode !== 'idle' && (
                <form onSubmit={submit} className="flw-panel">
                    {mode === 'decline' && (
                        <label className="flw-field">
                            <span className="flw-field__label">Evidence</span>
                            <input type="file" className="flw-input" accept={proofRules.types.map((type) => `.${type}`).join(',')} onChange={(event) => setProof(event.target.files?.[0] ?? null)} />
                            <span className="flw-field__hint">One JPEG, PNG or PDF under 8MB. Merge receipts, delivery notes and messages into one file.</span>
                        </label>
                    )}
                    <label className={`flw-field${error ? ' has-error' : ''}`}>
                        <span className="flw-field__label">{mode === 'accept' ? 'Why are you accepting it?' : 'What does your evidence show?'}</span>
                        <textarea value={comment} onChange={(event) => setComment(event.target.value)} rows={2} className="flw-input" />
                        {error && <span role="alert" className="flw-field__error">{error}</span>}
                    </label>
                    <div className="flw-actions">
                        <button type="submit" disabled={busy} className={`flw-btn ${mode === 'accept' ? 'flw-btn--secondary' : 'flw-btn--primary'}`}>
                            {busy && <span className="flw-btn__spinner" aria-hidden="true" />}
                            {busy ? 'Sending' : mode === 'accept' ? 'Accept and refund the customer' : 'Send dispute'}
                        </button>
                        <button type="button" onClick={() => { setMode('idle'); setError(null); }} className="flw-link-btn flw-link-btn--quiet">Cancel</button>
                    </div>
                </form>
            )}

            {chargeback.history.length > 0 && (
                <details className="flw-details">
                    <summary>History</summary>
                    <ol className="flw-timeline">
                        {chargeback.history.map((entry, index) => (
                            <li key={index}>
                                {entry.description}{entry.comment ? `: ${entry.comment}` : ''} <span className="flw-muted">{entry.date && new Date(entry.date).toLocaleString()}</span>
                            </li>
                        ))}
                    </ol>
                </details>
            )}
        </li>
    );
}
