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
    const numberRef = useRef<HTMLSpanElement>(null);
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

    return (
        <div className={className ?? 'grid max-w-sm gap-4 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800'}>
            <dl className={`grid gap-3 transition-opacity ${expired && !paid ? 'opacity-40' : ''}`}>
                <div>
                    <dt className="text-xs text-neutral-500 dark:text-neutral-400">Bank</dt>
                    <dd className="font-semibold">{account.bank_name}</dd>
                </div>
                <div>
                    <dt className="text-xs text-neutral-500 dark:text-neutral-400">Account number</dt>
                    <dd className="flex items-center gap-2">
                        <span ref={numberRef} className="text-2xl font-semibold tracking-wider tabular-nums">
                            {account.account_number}
                        </span>
                        <button
                            type="button"
                            onClick={copy}
                            disabled={expired || paid}
                            className="rounded-md border border-neutral-300 px-2 py-1 text-xs hover:bg-neutral-100 disabled:opacity-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                        >
                            {copyLabel}
                        </button>
                    </dd>
                </div>
                {amount > 0 && (
                    <div>
                        <dt className="text-xs text-neutral-500 dark:text-neutral-400">Amount to send</dt>
                        <dd className="font-semibold">
                            {account.currency ?? 'NGN'} {formatAmount(amount)}
                        </dd>
                    </div>
                )}
            </dl>

            {account.note && <p className="text-sm">{account.note}</p>}

            {children}

            <div aria-live="polite" className="text-sm">
                {paid ? (
                    <p className="font-medium text-green-700 dark:text-green-400">{paidMessage}</p>
                ) : expired ? (
                    <p className="text-red-700 dark:text-red-400">{expiredMessage}</p>
                ) : secondsLeft !== null ? (
                    <p className="text-neutral-500 dark:text-neutral-400">
                        Expires in <span className="tabular-nums">{formatDuration(secondsLeft)}</span>
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
