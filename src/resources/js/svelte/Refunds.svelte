<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { formatDate, statusLabel, statusTone, urlWith } from './admin';
    import Pager from './Pager.svelte';
    import type { FlutterwaveRefundsPanel } from './types';

    /** Refunds, from refunds()->panel($request->query()). Start a refund from the Transactions component. */
    let { panel, reloadOnly }: { panel: FlutterwaveRefundsPanel; reloadOnly?: string[] } = $props();

    let from = $state('');
    let to = $state('');
    let status = $state('');

    $effect.pre(() => {
        from = panel.filters.from ?? '';
        to = panel.filters.to ?? '';
        status = panel.filters.status ?? '';
    });

    function filter(event: SubmitEvent) {
        event.preventDefault();
        router.visit(urlWith({ from, to, status }), { preserveScroll: true, preserveState: true, ...(reloadOnly ? { only: reloadOnly } : {}) });
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
                    <option value="completed">Completed</option>
                    <option value="successful">Successful</option>
                    <option value="failed">Failed</option>
                </select>
                <span class="flw-select__chevron" aria-hidden="true"><svg viewBox="0 0 16 16" width="16" height="16"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
            </span>
        </label>
        <button type="submit" class="flw-btn flw-btn--primary flw-btn--sm">Show</button>
    </form>

    {#if panel.error}
        <p class="flw-notice flw-notice--error">Couldn’t load refunds: {panel.error}</p>
    {:else if panel.items.length === 0}
        <p class="flw-text">No refunds match these filters. Refund a payment from its transaction.</p>
    {:else}
        <div class="flw-table-wrap">
            <table class="flw-table">
                <thead><tr><th>Refunded</th><th>Status</th><th>Transaction</th><th>Reference</th><th>Reason</th><th>Date</th></tr></thead>
                <tbody>
                    {#each panel.items as refund (refund.id)}
                        <tr>
                            <td class="flw-strong">{Number(refund.amount_refunded ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                            <td><span class="flw-pill" data-tone={statusTone(refund.status)}>{statusLabel(refund.status)}</span></td>
                            <td>{refund.transaction_id}</td>
                            <td>{refund.flw_ref}</td>
                            <td>{refund.comment}</td>
                            <td>{formatDate(refund.created_at)}</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}

    <Pager page={panel.page} {reloadOnly} />
</div>
