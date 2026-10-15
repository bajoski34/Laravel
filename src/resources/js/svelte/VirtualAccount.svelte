<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
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
        paidMessage?: string;
        expiredMessage?: string;
        /** Added to the wrapper. */
        class?: string;
        children?: Snippet;
    }

    let {
        account,
        paid = false,
        reloadOnly,
        pollInterval = 5000,
        onPaid,
        onExpired,
        paidMessage = 'Payment received. Thank you!',
        expiredMessage = 'This account has expired. Refresh the page to get a new one.',
        class: className,
        children,
    }: Props = $props();

    // Falls back to the prop until the countdown ticks, so server-side rendering shows the time left.
    let ticked = $state<number | null>(null);
    const secondsLeft = $derived(ticked ?? account.expires_in ?? null);
    let copyLabel = $state('Copy');
    let numberEl = $state<HTMLElement | null>(null);
    let notifiedPaid = false;
    let notifiedExpired = false;

    const expired = $derived(secondsLeft === 0);
    const amount = $derived(Number(account.amount ?? 0));

    $effect(() => {
        const expiresIn = account.expires_in;
        void account.account_number; // restart the countdown when the account changes
        ticked = null;

        if (expiresIn !== undefined) {
            return startCountdown(expiresIn, (left) => (ticked = left));
        }
    });

    $effect(() => {
        if (paid || expired || !pollInterval) {
            return;
        }

        const only = reloadOnly;

        return startPolling(() => router.reload(only ? { only } : {}), pollInterval);
    });

    $effect(() => {
        if (paid && !notifiedPaid) {
            notifiedPaid = true;
            onPaid?.();
        }

        if (expired && !paid && !notifiedExpired) {
            notifiedExpired = true;
            onExpired?.();
        }
    });

    async function copy() {
        copyLabel = (await copyText(account.account_number, numberEl)) ? 'Copied' : 'Selected';
        window.setTimeout(() => (copyLabel = 'Copy'), 2000);
    }
</script>

<div class="flw flw-card flw-card--narrow flw-account{expired && !paid ? ' is-expired' : ''}{className ? ` ${className}` : ''}">
    <div class="flw-stack">
        <h3 class="flw-title">Pay by bank transfer</h3>
        <p class="flw-text">Send the exact amount to this account from your banking app.</p>
    </div>
    <hr class="flw-rule" />

    <div class="flw-account__details flw-stack" style="gap: 16px">
        <div class="flw-field">
            <span class="flw-field__label">Account number</span>
            <div class="flw-copyfield flw-account__number">
                <input bind:this={numberEl} class="flw-input" readonly value={account.account_number} aria-label="Account number" />
                <button type="button" disabled={expired || paid} class="flw-copyfield__btn" onclick={copy}>{copyLabel}</button>
            </div>
        </div>
        <dl class="flw-dl">
            <div><dt>Bank</dt><dd>{account.bank_name}</dd></div>
            {#if amount > 0}<div><dt>Amount to send</dt><dd>{account.currency ?? 'NGN'} {formatAmount(amount)}</dd></div>{/if}
        </dl>
        {#if account.note}<p class="flw-muted">{account.note}</p>{/if}
    </div>

    {@render children?.()}

    <div aria-live="polite">
        {#if paid}
            <p class="flw-notice flw-notice--success">{paidMessage}</p>
        {:else if expired}
            <p class="flw-notice flw-notice--error">{expiredMessage}</p>
        {:else if secondsLeft !== null}
            <p class="flw-countdown">
                {#if pollInterval > 0}<span class="flw-ring" aria-hidden="true"></span>{/if}
                Expires in <strong>{formatDuration(secondsLeft)}</strong>
                {#if pollInterval > 0}· We’ll update this page once your transfer arrives.{/if}
            </p>
        {/if}
    </div>
</div>
