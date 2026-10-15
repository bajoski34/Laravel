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
        /** Replaces the default classes. */
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

<div class={className ?? 'grid max-w-sm gap-4 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800'}>
    <dl class="grid gap-3 transition-opacity" class:opacity-40={expired && !paid}>
        <div>
            <dt class="text-xs text-neutral-500 dark:text-neutral-400">Bank</dt>
            <dd class="font-semibold">{account.bank_name}</dd>
        </div>
        <div>
            <dt class="text-xs text-neutral-500 dark:text-neutral-400">Account number</dt>
            <dd class="flex items-center gap-2">
                <span bind:this={numberEl} class="text-2xl font-semibold tracking-wider tabular-nums">{account.account_number}</span>
                <button
                    type="button"
                    disabled={expired || paid}
                    class="rounded-md border border-neutral-300 px-2 py-1 text-xs hover:bg-neutral-100 disabled:opacity-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                    onclick={copy}
                >
                    {copyLabel}
                </button>
            </dd>
        </div>
        {#if amount > 0}
            <div>
                <dt class="text-xs text-neutral-500 dark:text-neutral-400">Amount to send</dt>
                <dd class="font-semibold">{account.currency ?? 'NGN'} {formatAmount(amount)}</dd>
            </div>
        {/if}
    </dl>

    {#if account.note}
        <p class="text-sm">{account.note}</p>
    {/if}

    {@render children?.()}

    <div aria-live="polite" class="text-sm">
        {#if paid}
            <p class="font-medium text-green-700 dark:text-green-400">{paidMessage}</p>
        {:else if expired}
            <p class="text-red-700 dark:text-red-400">{expiredMessage}</p>
        {:else if secondsLeft !== null}
            <p class="text-neutral-500 dark:text-neutral-400">
                Expires in <span class="tabular-nums">{formatDuration(secondsLeft)}</span>
                {#if pollInterval > 0}· We’ll update this page once your transfer arrives.{/if}
            </p>
        {/if}
    </div>
</div>
