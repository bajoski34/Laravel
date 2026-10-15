<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { formatDate, statusLabel, statusTone, urlWith } from './admin';
import Pager from './Pager.vue';
import type { FlutterwaveRefundsPanel } from './types';

/** Refunds, from refunds()->panel($request->query()). Start a refund from the Transactions component. */
const props = defineProps<{ panel: FlutterwaveRefundsPanel; reloadOnly?: string[] }>();

const filters = reactive<Record<string, string>>({ ...props.panel.filters });

function filter() {
    router.visit(urlWith({ from: filters.from ?? '', to: filters.to ?? '', status: filters.status ?? '' }), { preserveScroll: true, preserveState: true, ...(props.reloadOnly ? { only: props.reloadOnly } : {}) });
}
</script>

<template>
    <div class="flw">
        <form class="flw-filters" @submit.prevent="filter">
            <label class="flw-field"><span class="flw-field__label">From</span><input v-model="filters.from" type="date" class="flw-input flw-input--sm" /></label>
            <label class="flw-field"><span class="flw-field__label">To</span><input v-model="filters.to" type="date" class="flw-input flw-input--sm" /></label>
            <label class="flw-field">
                <span class="flw-field__label">Status</span>
                <span class="flw-select">
                    <select v-model="filters.status" class="flw-input flw-input--sm">
                        <option :value="undefined">Any</option>
                        <option value="completed">Completed</option>
                        <option value="successful">Successful</option>
                        <option value="failed">Failed</option>
                    </select>
                    <span class="flw-select__chevron" aria-hidden="true"><svg viewBox="0 0 16 16" width="16" height="16"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
                </span>
            </label>
            <button type="submit" class="flw-btn flw-btn--primary flw-btn--sm">Show</button>
        </form>

        <p v-if="panel.error" class="flw-notice flw-notice--error">Couldn’t load refunds: {{ panel.error }}</p>
        <p v-else-if="panel.items.length === 0" class="flw-text">No refunds match these filters. Refund a payment from its transaction.</p>
        <div v-else class="flw-table-wrap">
            <table class="flw-table">
                <thead><tr><th>Refunded</th><th>Status</th><th>Transaction</th><th>Reference</th><th>Reason</th><th>Date</th></tr></thead>
                <tbody>
                    <tr v-for="refund in panel.items" :key="refund.id">
                        <td class="flw-strong">{{ Number(refund.amount_refunded ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</td>
                        <td><span class="flw-pill" :data-tone="statusTone(refund.status)">{{ statusLabel(refund.status) }}</span></td>
                        <td>{{ refund.transaction_id }}</td>
                        <td>{{ refund.flw_ref }}</td>
                        <td>{{ refund.comment }}</td>
                        <td>{{ formatDate(refund.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pager :page="panel.page" :reload-only="reloadOnly" />
    </div>
</template>
