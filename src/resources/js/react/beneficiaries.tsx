import { router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { firstError } from './admin';
import { BankSelect } from './bank-select';
import { Notice } from './icons';
import { Pager } from './pager';
import type { FlutterwaveBeneficiariesPanel, FlutterwaveErrors } from './types';

/** Saved transfer recipients, from beneficiaries()->panel($request->query()). */
export function Beneficiaries({ panel, reloadOnly }: { panel: FlutterwaveBeneficiariesPanel; reloadOnly?: string[] }) {
    const [adding, setAdding] = useState(panel.items.length === 0);
    const [form, setForm] = useState({ account_bank: '', account_number: '', beneficiary_name: '' });
    const [message, setMessage] = useState<{ text: string; ok: boolean } | null>(null);
    const [busy, setBusy] = useState(false);
    const [deleting, setDeleting] = useState<number | null>(null);

    const options = (text: string, onSuccess?: () => void) => ({
        preserveScroll: true,
        onSuccess: () => {
            setMessage({ text, ok: true });
            onSuccess?.();
        },
        onError: (errors: Record<string, string>) => setMessage({ text: firstError(errors as FlutterwaveErrors), ok: false }),
        onFinish: () => setBusy(false),
        ...(reloadOnly ? { only: reloadOnly } : {}),
    });

    const save = (event: FormEvent) => {
        event.preventDefault();
        setBusy(true);
        router.post(panel.urls!.create, form, options('Beneficiary saved.', () => {
            setForm({ account_bank: '', account_number: '', beneficiary_name: '' });
            setAdding(false);
        }));
    };

    return (
        <div className="flw">
            {message && <Notice text={message.text} ok={message.ok} />}

            {panel.urls && (adding ? (
                <form onSubmit={save} className="flw-card flw-card--narrow">
                    <h3 className="flw-title">Add a beneficiary</h3>
                    <hr className="flw-rule" />
                    <div className="flw-field">
                        <span className="flw-field__label">Bank</span>
                        <BankSelect banks={panel.banks} value={form.account_bank} onChange={(code) => setForm({ ...form, account_bank: code })} />
                        {panel.banks_error && <span className="flw-field__error">{panel.banks_error}</span>}
                    </div>
                    <label className="flw-field"><span className="flw-field__label">Account number</span><input required inputMode="numeric" maxLength={20} value={form.account_number} onChange={(event) => setForm({ ...form, account_number: event.target.value.replace(/\s/g, '') })} className="flw-input flw-num" /></label>
                    <label className="flw-field">
                        <span className="flw-field__label">Name</span>
                        <input maxLength={100} value={form.beneficiary_name} onChange={(event) => setForm({ ...form, beneficiary_name: event.target.value })} className="flw-input" />
                        <span className="flw-field__hint">Leave empty to use the name on the account.</span>
                    </label>
                    <div className="flw-actions">
                        <button type="submit" disabled={busy} className="flw-btn flw-btn--primary">
                            {busy && <span className="flw-btn__spinner" aria-hidden="true" />}
                            {busy ? 'Saving' : 'Save beneficiary'}
                        </button>
                        {panel.items.length > 0 && <button type="button" onClick={() => setAdding(false)} className="flw-link-btn flw-link-btn--quiet">Cancel</button>}
                    </div>
                </form>
            ) : (
                <button type="button" onClick={() => setAdding(true)} className="flw-btn flw-btn--outline flw-btn--sm">Add a beneficiary</button>
            ))}

            {panel.error ? (
                <p className="flw-notice flw-notice--error">Couldn’t load beneficiaries: {panel.error}</p>
            ) : panel.items.length === 0 ? (
                <p className="flw-text">No beneficiaries yet. Saved recipients make repeat transfers quicker.</p>
            ) : (
                <ul className="flw-rows">
                    {panel.items.map((beneficiary) => (
                        <li key={beneficiary.id}>
                            <div className="flw-row" style={{ alignItems: 'center' }}>
                                <div className="flw-stack">
                                    <span className="flw-strong">{beneficiary.full_name}</span>
                                    <span className="flw-meta"><span>{beneficiary.account_number}</span><span>{beneficiary.bank_name} ({beneficiary.bank_code})</span></span>
                                </div>
                                {beneficiary.urls && (deleting === beneficiary.id ? (
                                    <span className="flw-inline">
                                        <span>Delete {beneficiary.full_name}?</span>
                                        <button type="button" disabled={busy} onClick={() => { setBusy(true); router.delete(beneficiary.urls!.delete, options('Beneficiary deleted.')); setDeleting(null); }} className="flw-btn flw-btn--danger flw-btn--sm">Delete</button>
                                        <button type="button" onClick={() => setDeleting(null)} className="flw-link-btn flw-link-btn--quiet">Keep</button>
                                    </span>
                                ) : (
                                    <button type="button" onClick={() => setDeleting(beneficiary.id)} className="flw-btn flw-btn--outline flw-btn--sm">Delete</button>
                                ))}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <Pager page={panel.page} reloadOnly={reloadOnly} />
        </div>
    );
}
