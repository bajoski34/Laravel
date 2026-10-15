import { router } from '@inertiajs/react';
import { type ReactNode, useEffect, useRef, useState } from 'react';
import { copyText, formatAmount, formatDuration, startCountdown, startPolling } from './format';
import type { FlutterwaveVirtualAccount } from './types';

interface Props {
    /** From virtualAccounts()->dynamicFor() on the server. */
    account: FlutterwaveVirtualAccount;
    /**
     * Whether the order is paid, from your own props. Your ChargeCompleted listener
     * marks the order paid when the webhook arrives; while this is false the
     * component reloads the props below so the page notices.
     */
    paid?: boolean;
    /** Props to refresh while waiting, e.g. ['order']. Defaults to all props. */
    reloadOnly?: string[];
    /** How often to check for the payment, in milliseconds. 0 turns checking off. */
    pollInterval?: number;
    onPaid?: () => void;
    onExpired?: () => void;
    paidMessage?: ReactNode;
    expiredMessage?: ReactNode;
    className?: string;
    children?: ReactNode;
}

export function VirtualAccount({
    account,
    paid = false,
    reloadOnly,
    pollInterval = 5000,
    onPaid,
    onExpired,
    paidMessage = 'Payment received. Thank you!',
    expiredMessage = 'This account has expired. Refresh the page to get a new one.',
    className,
    children,
}: Props) {
    const secondsLeft = useCountdown(account);
    const expired = secondsLeft === 0;
    const [copyLabel, setCopyLabel] = useState('Copy');
    const numberRef = useRef<HTMLInputElement>(null);
    const notified = useRef({ paid: false, expired: false });

    useEffect(() => {
        if (paid && !notified.current.paid) {
            notified.current.paid = true;
            onPaid?.();
        }
    }, [paid, onPaid]);

    useEffect(() => {
        if (expired && !paid && !notified.current.expired) {
            notified.current.expired = true;
            onExpired?.();
        }
    }, [expired, paid, onExpired]);

    useEffect(() => {
        if (paid || expired || !pollInterval) {
            return;
        }

        return startPolling(() => router.reload(reloadOnly ? { only: reloadOnly } : {}), pollInterval);
    }, [paid, expired, pollInterval, reloadOnly?.join(',')]);

    const copy = async () => {
        setCopyLabel((await copyText(account.account_number, numberRef.current)) ? 'Copied' : 'Selected');
        window.setTimeout(() => setCopyLabel('Copy'), 2000);
    };

    const amount = Number(account.amount ?? 0);
    const expired_ = expired && !paid;

    return (
        <div className={`flw flw-card flw-card--narrow flw-account${expired_ ? ' is-expired' : ''}${className ? ` ${className}` : ''}`}>
            <div className="flw-stack">
                <h3 className="flw-title">Pay by bank transfer</h3>
                <p className="flw-text">Send the exact amount to this account from your banking app.</p>
            </div>
            <hr className="flw-rule" />

            <div className="flw-account__details flw-stack" style={{ gap: 16 }}>
                <div className="flw-field">
                    <span className="flw-field__label">Account number</span>
                    <div className="flw-copyfield flw-account__number">
                        <input ref={numberRef} className="flw-input" readOnly value={account.account_number} aria-label="Account number" />
                        <button type="button" onClick={copy} disabled={expired || paid} className="flw-copyfield__btn">{copyLabel}</button>
                    </div>
                </div>
                <dl className="flw-dl">
                    <div><dt>Bank</dt><dd>{account.bank_name}</dd></div>
                    {amount > 0 && <div><dt>Amount to send</dt><dd>{account.currency ?? 'NGN'} {formatAmount(amount)}</dd></div>}
                </dl>
                {account.note && <p className="flw-muted">{account.note}</p>}
            </div>

            {children}

            <div aria-live="polite">
                {paid ? (
                    <p className="flw-notice flw-notice--success">{paidMessage}</p>
                ) : expired ? (
                    <p className="flw-notice flw-notice--error">{expiredMessage}</p>
                ) : secondsLeft !== null ? (
                    <p className="flw-countdown">
                        {pollInterval > 0 && <span className="flw-ring" aria-hidden="true" />}
                        Expires in <strong>{formatDuration(secondsLeft)}</strong>
                        {pollInterval > 0 && ' · We’ll update this page once your transfer arrives.'}
                    </p>
                ) : null}
            </div>
        </div>
    );
}

/** Seconds until the account expires, or null when the account doesn't say. */
function useCountdown(account: FlutterwaveVirtualAccount): number | null {
    const expiresIn = account.expires_in;
    const [secondsLeft, setSecondsLeft] = useState<number | null>(expiresIn ?? null);

    useEffect(() => {
        if (expiresIn === undefined) {
            setSecondsLeft(null);

            return;
        }

        return startCountdown(expiresIn, setSecondsLeft);
    }, [account.account_number, expiresIn]);

    return secondsLeft;
}
