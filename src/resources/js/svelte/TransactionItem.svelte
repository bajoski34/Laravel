<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { firstError, formatDate, formatMoney, statusLabel, statusTone } from './admin';
    import type { FlutterwaveErrors, FlutterwaveTransactionsPanel } from './types';

    interface Props {
        transaction: FlutterwaveTransactionsPanel['items'][number];
        panel: FlutterwaveTransactionsPanel;
        onVisit: (params: Record<string, string | null>) => void;
    }

    let { transaction, panel, onVisit }: Props = $props();

    let refunding = $state(false);
    let amount = $state('');
    let comment = $state('');
    let confirming = $state(false);
    let message = $state<{ text: string; ok: boolean } | null>(null);
    let busy = $state(false);
    const open = $derived(panel.timeline?.transaction_id === String(transaction.id));
    const total = $derived(formatMoney(transaction.currency, transaction.amount));

    function post(url: string, data: Record<string, string>, success: string) {
        busy = true;
        router.post(url, data, {
            preserveScroll: true,
            onSuccess: () => (message = { text: success, ok: true }),
            onError: (errors) => (message = { text: firstError(errors as FlutterwaveErrors), ok: false }),
            onFinish: () => (busy = false),
        });
    }

    function refund(event: SubmitEvent) {
        event.preventDefault();

        if (!confirming) {
            confirming = true;

            return;
        }

        const data = Object.fromEntries(Object.entries({ amount, comment }).filter(([, value]) => value !== ''));
        post(transaction.urls!.refund!, data, amount ? `Refund of ${formatMoney(transaction.currency, amount)} started.` : `Full refund of ${total} started.`);
        refunding = false;
        confirming = false;
    }
</script>

<li>
    <div class="flw-row">
        <div class="flw-stack">
            <span class="flw-amount">{total}</span>
            <span class="flw-meta">
                <span>{transaction.customer_name || transaction.customer_email}</span>
                {#if transaction.customer_name && transaction.customer_email}<span>{transaction.customer_email}</span>{/if}
                <span>{transaction.tx_ref}</span>
                <span>{formatDate(transaction.created_at)}</span>
                {#if transaction.payment_type}<span>{transaction.payment_type.replace(/_/g, ' ')}</span>{/if}
            </span>
        </div>
        <span class="flw-pill" data-tone={statusTone(transaction.status)}>{statusLabel(transaction.status)}</span>
    </div>

    <div class="flw-actions">
        <button type="button" class="flw-link-btn" onclick={() => onVisit({ transaction: open ? null : String(transaction.id) })}>{open ? 'Hide timeline' : 'Timeline'}</button>
        {#if transaction.urls?.refund && !refunding}<button type="button" class="flw-btn flw-btn--outline flw-btn--sm" onclick={() => (refunding = true)}>Refund</button>{/if}
        {#if transaction.urls}<button type="button" disabled={busy} class="flw-btn flw-btn--outline flw-btn--sm" onclick={() => post(transaction.urls!.resend_webhook, {}, 'Webhook sent again.')}>Resend webhook</button>{/if}
    </div>

    {#if message}
        <p role={message.ok ? 'status' : 'alert'} class="flw-notice {message.ok ? 'flw-notice--success' : 'flw-notice--error'}">{message.text}</p>
    {/if}

    {#if refunding}
        <form class="flw-panel" onsubmit={refund}>
            <label class="flw-field"><span class="flw-field__label">Amount to refund</span><input inputmode="decimal" bind:value={amount} oninput={() => (confirming = false)} placeholder="Leave empty to refund {total} in full" class="flw-input" /></label>
            <label class="flw-field"><span class="flw-field__label">Reason</span><input maxlength="255" bind:value={comment} placeholder="Damaged on delivery" class="flw-input" /></label>
            {#if confirming}<p class="flw-notice flw-notice--info">Refund {amount ? formatMoney(transaction.currency, amount) : `${total} in full`}? Refunds can’t be undone.</p>{/if}
            <div class="flw-actions">
                <button type="submit" disabled={busy} class="flw-btn flw-btn--primary">{confirming ? 'Confirm refund' : 'Refund'}</button>
                <button type="button" class="flw-link-btn flw-link-btn--quiet" onclick={() => { refunding = false; confirming = false; }}>Cancel</button>
            </div>
        </form>
    {/if}

    {#if open && panel.timeline}
        {#if panel.timeline.error}
            <p class="flw-error-text">{panel.timeline.error}</p>
        {:else}
            <ol class="flw-timeline">
                {#each panel.timeline.events as event, index (index)}
                    <li>{event.note ?? event.action} <span class="flw-muted">{formatDate(event.created_at)}</span></li>
                {/each}
            </ol>
        {/if}
    {/if}
</li>
