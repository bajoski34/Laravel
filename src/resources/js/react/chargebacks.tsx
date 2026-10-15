import { router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { chargebackStatus, checkProof, firstError, formatDeadline, toneClasses } from './admin';
import type { FlutterwaveChargeback, FlutterwaveChargebackPanel, FlutterwaveErrors } from './types';

interface Props {
    /** From chargebacks()->panel() on the server. */
    panel: FlutterwaveChargebackPanel;
    /** Replaces the default classes on the list. */
    className?: string;
}

export function Chargebacks({ panel, className }: Props) {
    if (panel.error) {
        return <p className="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-400/10 dark:text-red-300">Couldn’t load chargebacks: {panel.error}</p>;
    }

    if (panel.items.length === 0) {
        return <p className="rounded-lg border border-dashed border-neutral-300 p-6 text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">No chargebacks. When a customer disputes a payment with their bank, it shows up here.</p>;
    }

    return (
        <div className="grid gap-3">
            <ul className={className ?? 'grid gap-3'}>
                {panel.items.map((chargeback) => (
                    <ChargebackItem key={chargeback.id} chargeback={chargeback} proofRules={panel.proof} />
                ))}
            </ul>
            {panel.page && panel.page.total_pages > 1 && (
                <p className="text-xs text-neutral-500 dark:text-neutral-400">
                    Page {panel.page.current_page} of {panel.page.total_pages}, {panel.page.total} in total.
                </p>
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
        <li className="grid gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="grid gap-0.5">
                    <span className="text-lg font-semibold tabular-nums">{chargeback.amount?.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    <span className="text-xs text-neutral-500 dark:text-neutral-400">
                        {chargeback.tx_ref} · {chargeback.flw_ref}
                    </span>
                </div>
                <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${toneClasses[status.tone]}`}>{status.label}</span>
            </div>

            {chargeback.respond_within !== null && (
                <p className={`text-sm ${urgent ? 'font-medium text-red-700 dark:text-red-400' : 'text-neutral-600 dark:text-neutral-300'}`}>
                    {chargeback.respond_within > 0 ? `Respond within ${formatDeadline(chargeback.respond_within)}, or the customer is refunded automatically.` : 'The response deadline has passed.'}
                </p>
            )}

            {chargeback.comment && <p className="text-sm text-neutral-600 dark:text-neutral-300">Customer’s reason: {chargeback.comment}</p>}

            {done && (
                <p role="status" className="text-sm font-medium text-green-700 dark:text-green-400">
                    {done}
                </p>
            )}

            {chargeback.urls && mode === 'idle' && !done && (
                <div className="flex flex-wrap gap-2">
                    <button type="button" onClick={() => setMode('decline')} className="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-700 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
                        Dispute with proof
                    </button>
                    <button type="button" onClick={() => setMode('accept')} className="rounded-md border border-neutral-300 px-3 py-1.5 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">
                        Accept and refund
                    </button>
                </div>
            )}

            {mode !== 'idle' && (
                <form onSubmit={submit} className="grid gap-3 rounded-lg bg-neutral-50 p-3 dark:bg-neutral-900">
                    {mode === 'decline' && (
                        <label className="grid gap-1 text-sm">
                            <span className="font-medium">Evidence</span>
                            <input type="file" accept={proofRules.types.map((type) => `.${type}`).join(',')} onChange={(event) => setProof(event.target.files?.[0] ?? null)} className="text-sm" />
                            <span className="text-xs text-neutral-500 dark:text-neutral-400">One JPEG, PNG or PDF under 8MB. Merge receipts, delivery notes and messages into one file.</span>
                        </label>
                    )}
                    <label className="grid gap-1 text-sm">
                        <span className="font-medium">{mode === 'accept' ? 'Why are you accepting it?' : 'What does your evidence show?'}</span>
                        <textarea value={comment} onChange={(event) => setComment(event.target.value)} rows={2} className="rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-950" />
                    </label>
                    {error && (
                        <p role="alert" className="text-sm text-red-700 dark:text-red-400">
                            {error}
                        </p>
                    )}
                    <div className="flex flex-wrap gap-2">
                        <button type="submit" disabled={busy} className="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-neutral-900">
                            {busy ? 'Sending…' : mode === 'accept' ? 'Accept and refund the customer' : 'Send dispute'}
                        </button>
                        <button type="button" onClick={() => { setMode('idle'); setError(null); }} className="rounded-md px-3 py-1.5 text-sm text-neutral-600 hover:underline dark:text-neutral-300">
                            Cancel
                        </button>
                    </div>
                </form>
            )}

            {chargeback.history.length > 0 && (
                <details className="text-sm">
                    <summary className="cursor-pointer text-neutral-500 dark:text-neutral-400">History</summary>
                    <ol className="mt-2 grid gap-1.5 border-l border-neutral-200 pl-3 dark:border-neutral-800">
                        {chargeback.history.map((entry, index) => (
                            <li key={index} className="grid">
                                <span>{entry.description}{entry.comment ? `: ${entry.comment}` : ''}</span>
                                <span className="text-xs text-neutral-500 dark:text-neutral-400">{entry.date && new Date(entry.date).toLocaleString()}</span>
                            </li>
                        ))}
                    </ol>
                </details>
            )}
        </li>
    );
}
