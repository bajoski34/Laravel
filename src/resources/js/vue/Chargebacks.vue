<script setup lang="ts">
import ChargebackItem from './ChargebackItem.vue';
import type { FlutterwaveChargebackPanel } from './types';

defineProps<{
    /** From chargebacks()->panel() on the server. */
    panel: FlutterwaveChargebackPanel;
}>();
</script>

<template>
    <div class="flw">
        <p v-if="panel.error" class="flw-notice flw-notice--error">Couldn’t load chargebacks: {{ panel.error }}</p>
        <p v-else-if="panel.items.length === 0" class="flw-text">No chargebacks. When a customer disputes a payment with their bank, it shows up here.</p>
        <ul v-else class="flw-rows">
            <ChargebackItem v-for="chargeback in panel.items" :key="chargeback.id" :chargeback="chargeback" :proof-rules="panel.proof" />
        </ul>
        <p v-if="panel.page && panel.page.total_pages > 1" class="flw-muted">Page {{ panel.page.current_page }} of {{ panel.page.total_pages }}, {{ panel.page.total }} in total.</p>
    </div>
</template>
