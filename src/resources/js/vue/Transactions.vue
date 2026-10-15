<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { formatMoney, urlWith } from './admin';
import Pager from './Pager.vue';
import TransactionItem from './TransactionItem.vue';
import type { FlutterwaveTransactionsPanel } from './types';

const props = defineProps<{
    /** From transactions()->panel($request->query()) on the server. */
    panel: FlutterwaveTransactionsPanel;
    /** The prop name you passed the panel as, so filtering only reloads it. Defaults to all props. */
    reloadOnly?: string[];
}>();

const filters = reactive<Record<string, string>>({ ...props.panel.filters });
const fee = reactive({ amount: '', currency: props.panel.items[0]?.currency ?? 'NGN', type: '' });

function visit(params: Record<string, string | null>) {
    router.visit(urlWith(params), { preserveScroll: true, preserveState: true, ...(props.reloadOnly ? { only: props.reloadOnly } : {}) });
}

function filter() {
    visit({ from: filters.from ?? '', to: filters.to ?? '', status: filters.status ?? '', customer_email: filters.customer_email ?? '', tx_ref: filters.tx_ref ?? '', transaction: null });
}

function checkFee() {
    visit({ fee_amount: fee.amount, fee_currency: fee.currency, fee_type: fee.type });
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
                        <option value="successful">Successful</option>
                        <option value="failed">Failed</option>
                        <option value="pending">Pending</option>
                    </select>
                    <span class="flw-select__chevron" aria-hidden="true"><svg viewBox="0 0 16 16" width="16" height="16"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
                </span>
            </label>
            <label class="flw-field"><span class="flw-field__label">Customer email</span><input v-model="filters.customer_email" type="email" placeholder="jane@example.com" class="flw-input flw-input--sm" /></label>
            <label class="flw-field"><span class="flw-field__label">Reference</span><input v-model="filters.tx_ref" placeholder="ORDER-42" class="flw-input flw-input--sm" /></label>
            <button type="submit" class="flw-btn flw-btn--primary flw-btn--sm">Show</button>
        </form>

        <p v-if="panel.error" class="flw-notice flw-notice--error">Couldn’t load transactions: {{ panel.error }}</p>
        <p v-else-if="panel.items.length === 0" class="flw-text">No transactions match these filters.</p>
        <ul v-else class="flw-rows">
            <TransactionItem v-for="transaction in panel.items" :key="transaction.id" :transaction="transaction" :panel="panel" @visit="visit" />
        </ul>

        <Pager :page="panel.page" :reload-only="reloadOnly" />

        <details class="flw-details">
            <summary>Check a fee</summary>
            <form class="flw-filters" @submit.prevent="checkFee">
                <label class="flw-field"><span class="flw-field__label">Amount</span><input v-model="fee.amount" required inputmode="decimal" placeholder="10000" class="flw-input flw-input--sm" /></label>
                <label class="flw-field"><span class="flw-field__label">Currency</span><input v-model="fee.currency" required maxlength="3" class="flw-input flw-input--sm" /></label>
                <label class="flw-field">
                    <span class="flw-field__label">Paid by</span>
                    <span class="flw-select">
                        <select v-model="fee.type" class="flw-input flw-input--sm">
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
            <template v-if="panel.fee">
                <p v-if="panel.fee.error" class="flw-error-text">{{ panel.fee.error }}</p>
                <p v-else class="flw-text">
                    Flutterwave’s fee is <strong>{{ formatMoney(panel.fee.currency ?? null, panel.fee.fee ?? null) }}</strong>, so the customer is charged {{ formatMoney(panel.fee.currency ?? null, panel.fee.charge_amount ?? null) }}{{ panel.fee.stamp_duty_fee ? `, including ${formatMoney(panel.fee.currency ?? null, panel.fee.stamp_duty_fee)} stamp duty` : '' }}.
                </p>
            </template>
        </details>
    </div>
</template>
