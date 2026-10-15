<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { formatDate, formatMoney, statusLabel, statusTone, urlWith } from './admin';
    import Pager from './Pager.svelte';
    import type { FlutterwaveSettlementsPanel } from './types';

    /** Settlements, from settlements()->panel($request->query()), with a breakdown of the one picked. */
    let { panel, reloadOnly }: { panel: FlutterwaveSettlementsPanel; reloadOnly?: string[] } = $props();

    const amount = (value?: number) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
    let from = $state('');
    let to = $state('');

    $effect.pre(() => {
        from = panel.filters.from ?? '';
        to = panel.filters.to ?? '';
    });

    function visit(params: Record<string, string | null>) {
        router.visit(urlWith(params), { preserveScroll: true, preserveState: true, ...(reloadOnly ? { only: reloadOnly } : {}) });
    }

    function filter(event: SubmitEvent) {
        event.preventDefault();
        visit({ from, to, settlement: null });
    }
</script>

<div class="flw">
    <form class="flw-filters" onsubmit={filter}>
        <label class="flw-field"><span class="flw-field__label">From</span><input type="date" bind:value={from} class="flw-input flw-input--sm" /></label>
        <label class="flw-field"><span class="flw-field__label">To</span><input type="date" bind:value={to} class="flw-input flw-input--sm" /></label>
        <button type="submit" class="flw-btn flw-btn--primary flw-btn--sm">Show</button>
    </form>

    {#if panel.error}
        <p class="flw-notice flw-notice--error">Couldn’t load settlements: {panel.error}</p>
    {:else if panel.items.length === 0}
        <p class="flw-text">No settlements in this period.</p>
    {:else}
        <ul class="flw-rows">
            {#each panel.items as settlement (settlement.id)}
                {@const open = panel.selected?.id === settlement.id}
                <li>
                    <div class="flw-row">
                        <div class="flw-stack">
                            <span class="flw-amount">{formatMoney(settlement.currency ?? null, settlement.net_amount ?? null)}</span>
                            <span class="flw-meta">
                                <span>{settlement.transaction_count ?? 0} {settlement.transaction_count === 1 ? 'payment' : 'payments'} on {formatDate(settlement.transaction_date, false)}</span>
                                <span>Due {formatDate(settlement.due_date, false)}</span>
                                {#if settlement.processed_date}<span>Paid {formatDate(settlement.processed_date, false)}</span>{/if}
                            </span>
                        </div>
                        <span class="flw-pill" data-tone={statusTone(settlement.status)}>{statusLabel(settlement.status)}</span>
                    </div>
                    {#if settlement.flag_message}<p class="flw-notice flw-notice--warning">{settlement.flag_message}</p>{/if}
                    <button type="button" class="flw-link-btn" onclick={() => visit({ settlement: open ? null : String(settlement.id) })}>{open ? 'Hide details' : 'Details'}</button>
                    {#if open && panel.selected}
                        {@const selected = panel.selected}
                        <dl class="flw-dl">
                            <div><dt>Collected</dt><dd>{formatMoney(selected.currency ?? null, selected.gross_amount ?? null)}</dd></div>
                            <div><dt>Flutterwave fees</dt><dd>{formatMoney(selected.currency ?? null, (selected.app_fee ?? 0) + (selected.merchant_fee ?? 0) + (selected.stampduty_charge ?? 0))}</dd></div>
                            <div><dt>Refunds and chargebacks</dt><dd>{formatMoney(selected.currency ?? null, (selected.refund ?? 0) + (selected.chargeback ?? 0))}</dd></div>
                            <div><dt>Paid to you</dt><dd>{formatMoney(selected.currency ?? null, selected.net_amount ?? null)}</dd></div>
                        </dl>
                        {#if selected.transactions.length}
                            <div class="flw-table-wrap">
                                <table class="flw-table">
                                    <thead><tr><th>Reference</th><th>Customer</th><th>Charged</th><th>Fee</th><th>Settled</th></tr></thead>
                                    <tbody>
                                        {#each selected.transactions as transaction, index (transaction.id ?? index)}
                                            <tr>
                                                <td>{transaction.tx_ref ?? transaction.id}</td>
                                                <td>{transaction.customer_email}</td>
                                                <td>{amount(transaction.charged_amount)}</td>
                                                <td>{amount(transaction.app_fee)}</td>
                                                <td class="flw-strong">{amount(transaction.settlement_amount)}</td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                        {/if}
                    {/if}
                </li>
            {/each}
        </ul>
    {/if}

    <Pager page={panel.page} {reloadOnly} />
</div>
