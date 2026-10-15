<script setup lang="ts">
import ChargebackItem from './ChargebackItem.vue';
import type { FlutterwaveChargebackPanel } from './types';

defineProps<{
    /** From chargebacks()->panel() on the server. */
    panel: FlutterwaveChargebackPanel;
}>();
</script>

<template>
    <p v-if="panel.error" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-400/10 dark:text-red-300">Couldn’t load chargebacks: {{ panel.error }}</p>
    <p v-else-if="panel.items.length === 0" class="rounded-lg border border-dashed border-neutral-300 p-6 text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
        No chargebacks. When a customer disputes a payment with their bank, it shows up here.
    </p>
    <div v-else class="grid gap-3">
        <ul class="grid gap-3">
            <ChargebackItem v-for="chargeback in panel.items" :key="chargeback.id" :chargeback="chargeback" :proof-rules="panel.proof" />
        </ul>
        <p v-if="panel.page && panel.page.total_pages > 1" class="text-xs text-neutral-500 dark:text-neutral-400">
            Page {{ panel.page.current_page }} of {{ panel.page.total_pages }}, {{ panel.page.total }} in total.
        </p>
    </div>
</template>
