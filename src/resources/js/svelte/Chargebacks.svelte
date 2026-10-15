<script lang="ts">
    import ChargebackItem from './ChargebackItem.svelte';
    import type { FlutterwaveChargebackPanel } from './types';

    interface Props {
        /** From chargebacks()->panel() on the server. */
        panel: FlutterwaveChargebackPanel;
        /** Replaces the default classes on the list. */
        class?: string;
    }

    let { panel, class: className }: Props = $props();
</script>

{#if panel.error}
    <p class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-400/10 dark:text-red-300">Couldn’t load chargebacks: {panel.error}</p>
{:else if panel.items.length === 0}
    <p class="rounded-lg border border-dashed border-neutral-300 p-6 text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
        No chargebacks. When a customer disputes a payment with their bank, it shows up here.
    </p>
{:else}
    <div class="grid gap-3">
        <ul class={className ?? 'grid gap-3'}>
            {#each panel.items as chargeback (chargeback.id)}
                <ChargebackItem {chargeback} proofRules={panel.proof} />
            {/each}
        </ul>
        {#if panel.page && panel.page.total_pages > 1}
            <p class="text-xs text-neutral-500 dark:text-neutral-400">Page {panel.page.current_page} of {panel.page.total_pages}, {panel.page.total} in total.</p>
        {/if}
    </div>
{/if}
