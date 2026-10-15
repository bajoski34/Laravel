<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { formatMoney, urlWith } from './admin';
    import Pager from './Pager.svelte';
    import TransactionItem from './TransactionItem.svelte';
    import type { FlutterwaveTransactionsPanel } from './types';

    interface Props {
        /** From transactions()->panel($request->query()) on the server. */
        panel: FlutterwaveTransactionsPanel;
        /** The prop name you passed the panel as, so filtering only reloads it. Defaults to all props. */
        reloadOnly?: string[];
    }

    let { panel, reloadOnly }: Props = $props();

    let from = $state('');
    let to = $state('');
    let status = $state('');
    let email = $state('');
    let reference = $state('');
    let feeAmount = $state('');
    let feeCurrency = $state('');
    let feeType = $state('');

    // Start from the server's filters; after that the form owns them.
    $effect.pre(() => {
        from = panel.filters.from ?? '';
        to = panel.filters.to ?? '';
        status = panel.filters.status ?? '';
        email = panel.filters.customer_email ?? '';
        reference = panel.filters.tx_ref ?? '';
        // Untracked, so typing in the fee form doesn't re-run this and reset the filters.
        const currency = panel.items[0]?.currency ?? 'NGN';
        untrack(() => (feeCurrency ||= currency));
    });

    function visit(params: Record<string, string | null>) {
        router.visit(urlWith(params), { preserveScroll: true, preserveState: true, ...(reloadOnly ? { only: reloadOnly } : {}) });
    }

    function filter(event: SubmitEvent) {
        event.preventDefault();
        visit({ from, to, status, customer_email: email, tx_ref: reference, transaction: null });
    }

    function checkFee(event: SubmitEvent) {
        event.preventDefault();
        visit({ fee_amount: feeAmount, fee_currency: feeCurrency, fee_type: feeType });
    }
</script>

<div class="flw">
    <form class="flw-filters" onsubmit={filter}>
        <label class="flw-field"><span class="flw-field__label">From</span><input type="date" bind:value={from} class="flw-input flw-input--sm" /></label>
        <label class="flw-field"><span class="flw-field__label">To</span><input type="date" bind:value={to} class="flw-input flw-input--sm" /></label>
        <label class="flw-field">
            <span class="flw-field__label">Status</span>
            <span class="flw-select">
                <select bind:value={status} class="flw-input flw-input--sm">
                    <option value="">Any</option>
                    <option value="successful">Successful</option>
                    <option value="failed">Failed</option>
                    <option value="pending">Pending</option>
                </select>
                <span class="flw-select__chevron" aria-hidden="true"><svg viewBox="0 0 16 16" width="16" height="16"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
            </span>
        </label>
        <label class="flw-field"><span class="flw-field__label">Customer email</span><input type="email" bind:value={email} placeholder="jane@example.com" class="flw-input flw-input--sm" /></label>
        <label class="flw-field"><span class="flw-field__label">Reference</span><input bind:value={reference} placeholder="ORDER-42" class="flw-input flw-input--sm" /></label>
        <button type="submit" class="flw-btn flw-btn--primary flw-btn--sm">Show</button>
    </form>

    {#if panel.error}
        <p class="flw-notice flw-notice--error">Couldn’t load transactions: {panel.error}</p>
    {:else if panel.items.length === 0}
        <p class="flw-text">No transactions match these filters.</p>
    {:else}
        <ul class="flw-rows">
            {#each panel.items as transaction (transaction.id)}
                <TransactionItem {transaction} {panel} onVisit={visit} />
            {/each}
        </ul>
    {/if}

    <Pager page={panel.page} {reloadOnly} />

    <details class="flw-details">
        <summary>Check a fee</summary>
        <form class="flw-filters" onsubmit={checkFee}>
            <label class="flw-field"><span class="flw-field__label">Amount</span><input required inputmode="decimal" bind:value={feeAmount} placeholder="10000" class="flw-input flw-input--sm" /></label>
            <label class="flw-field"><span class="flw-field__label">Currency</span><input required maxlength="3" bind:value={feeCurrency} class="flw-input flw-input--sm" /></label>
            <label class="flw-field">
                <span class="flw-field__label">Paid by</span>
                <span class="flw-select">
                <select bind:value={feeType} class="flw-input flw-input--sm">
                    <option value="">Any method</option>
                    <option value="card">Card</option>
                    <option value="debit_ng_account">Bank account</option>
                    <option value="mobilemoney">Mobile money</option>
                    <option value="bank_transfer">Bank transfer</option>
                </select>
                <span class="flw-select__chevron" aria-hidden="true"><svg viewBox="0 0 16 16" width="16" height="16"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
            </span>
            </label>
            <button type="submit" class="flw-btn flw-btn--outline flw-btn--sm">Check</button>
        </form>
        {#if panel.fee}
            {#if panel.fee.error}
                <p class="flw-error-text">{panel.fee.error}</p>
            {:else}
                <p class="flw-text">
                    Flutterwave’s fee is <strong>{formatMoney(panel.fee.currency ?? null, panel.fee.fee ?? null)}</strong>, so the customer is charged {formatMoney(panel.fee.currency ?? null, panel.fee.charge_amount ?? null)}{panel.fee.stamp_duty_fee ? `, including ${formatMoney(panel.fee.currency ?? null, panel.fee.stamp_duty_fee)} stamp duty` : ''}.
                </p>
            {/if}
        {/if}
    </details>
</div>
