<script lang="ts">
    import ChargebackItem from './ChargebackItem.svelte';
    import type { FlutterwaveChargebackPanel } from './types';

    interface Props {
        /** From chargebacks()->panel() on the server. */
        panel: FlutterwaveChargebackPanel;
        /** Added to the wrapper. */
        class?: string;
    }

    let { panel, class: className }: Props = $props();
</script>

<div class="flw{className ? ` ${className}` : ''}">
    {#if panel.error}
        <p class="flw-notice flw-notice--error">Couldn’t load chargebacks: {panel.error}</p>
    {:else if panel.items.length === 0}
        <p class="flw-text">No chargebacks. When a customer disputes a payment with their bank, it shows up here.</p>
    {:else}
        <ul class="flw-rows">
            {#each panel.items as chargeback (chargeback.id)}
                <ChargebackItem {chargeback} proofRules={panel.proof} />
            {/each}
        </ul>
    {/if}
    {#if panel.page && panel.page.total_pages > 1}
        <p class="flw-muted">Page {panel.page.current_page} of {panel.page.total_pages}, {panel.page.total} in total.</p>
    {/if}
</div>
