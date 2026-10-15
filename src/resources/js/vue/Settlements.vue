<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { formatDate, formatMoney, statusLabel, statusTone, urlWith } from './admin';
import Pager from './Pager.vue';
import type { FlutterwaveSettlementsPanel } from './types';

/** Settlements, from settlements()->panel($request->query()), with a breakdown of the one picked. */
const props = defineProps<{ panel: FlutterwaveSettlementsPanel; reloadOnly?: string[] }>();

const filters = reactive<Record<string, string>>({ ...props.panel.filters });
const amount = (value?: number) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 });

function visit(params: Record<string, string | null>) {
    router.visit(urlWith(params), { preserveScroll: true, preserveState: true, ...(props.reloadOnly ? { only: props.reloadOnly } : {}) });
}
</script>

<template>
    <div class="flw">
        <form class="flw-filters" @submit.prevent="visit({ from: filters.from ?? '', to: filters.to ?? '', settlement: null })">
            <label class="flw-field"><span class="flw-field__label">From</span><input v-model="filters.from" type="date" class="flw-input flw-input--sm" /></label>
            <label class="flw-field"><span class="flw-field__label">To</span><input v-model="filters.to" type="date" class="flw-input flw-input--sm" /></label>
            <button type="submit" class="flw-btn flw-btn--primary flw-btn--sm">Show</button>
        </form>

        <p v-if="panel.error" class="flw-notice flw-notice--error">Couldn’t load settlements: {{ panel.error }}</p>
        <p v-else-if="panel.items.length === 0" class="flw-text">No settlements in this period.</p>
        <ul v-else class="flw-rows">
            <li v-for="settlement in panel.items" :key="settlement.id">
                <div class="flw-row">
                    <div class="flw-stack">
                        <span class="flw-amount">{{ formatMoney(settlement.currency ?? null, settlement.net_amount ?? null) }}</span>
                        <span class="flw-meta">
                            <span>{{ settlement.transaction_count ?? 0 }} {{ settlement.transaction_count === 1 ? 'payment' : 'payments' }} on {{ formatDate(settlement.transaction_date, false) }}</span>
                            <span>Due {{ formatDate(settlement.due_date, false) }}</span>
                            <span v-if="settlement.processed_date">Paid {{ formatDate(settlement.processed_date, false) }}</span>
                        </span>
                    </div>
                    <span class="flw-pill" :data-tone="statusTone(settlement.status)">{{ statusLabel(settlement.status) }}</span>
                </div>
                <p v-if="settlement.flag_message" class="flw-notice flw-notice--warning">{{ settlement.flag_message }}</p>
                <button type="button" class="flw-link-btn" @click="visit({ settlement: panel.selected?.id === settlement.id ? null : String(settlement.id) })">{{ panel.selected?.id === settlement.id ? 'Hide details' : 'Details' }}</button>
                <template v-if="panel.selected && panel.selected.id === settlement.id">
                    <dl class="flw-dl">
                        <div><dt>Collected</dt><dd>{{ formatMoney(panel.selected.currency ?? null, panel.selected.gross_amount ?? null) }}</dd></div>
                        <div><dt>Flutterwave fees</dt><dd>{{ formatMoney(panel.selected.currency ?? null, (panel.selected.app_fee ?? 0) + (panel.selected.merchant_fee ?? 0) + (panel.selected.stampduty_charge ?? 0)) }}</dd></div>
                        <div><dt>Refunds and chargebacks</dt><dd>{{ formatMoney(panel.selected.currency ?? null, (panel.selected.refund ?? 0) + (panel.selected.chargeback ?? 0)) }}</dd></div>
                        <div><dt>Paid to you</dt><dd>{{ formatMoney(panel.selected.currency ?? null, panel.selected.net_amount ?? null) }}</dd></div>
                    </dl>
                    <div v-if="panel.selected.transactions.length" class="flw-table-wrap">
                        <table class="flw-table">
                            <thead><tr><th>Reference</th><th>Customer</th><th>Charged</th><th>Fee</th><th>Settled</th></tr></thead>
                            <tbody>
                                <tr v-for="(transaction, index) in panel.selected.transactions" :key="transaction.id ?? index">
                                    <td>{{ transaction.tx_ref ?? transaction.id }}</td>
                                    <td>{{ transaction.customer_email }}</td>
                                    <td>{{ amount(transaction.charged_amount) }}</td>
                                    <td>{{ amount(transaction.app_fee) }}</td>
                                    <td class="flw-strong">{{ amount(transaction.settlement_amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </li>
        </ul>

        <Pager :page="panel.page" :reload-only="reloadOnly" />
    </div>
</template>
