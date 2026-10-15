<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { firstError, formatDate, formatMoney, statusLabel, statusTone } from './admin';
import type { FlutterwaveErrors, FlutterwaveTransactionsPanel } from './types';

const props = defineProps<{ transaction: FlutterwaveTransactionsPanel['items'][number]; panel: FlutterwaveTransactionsPanel }>();
const emit = defineEmits<{ visit: [params: Record<string, string | null>] }>();

const refunding = ref(false);
const amount = ref('');
const comment = ref('');
const confirming = ref(false);
const message = ref<{ text: string; ok: boolean } | null>(null);
const busy = ref(false);
const open = computed(() => props.panel.timeline?.transaction_id === String(props.transaction.id));
const total = computed(() => formatMoney(props.transaction.currency, props.transaction.amount));

function post(url: string, data: Record<string, string>, success: string) {
    busy.value = true;
    router.post(url, data, {
        preserveScroll: true,
        onSuccess: () => (message.value = { text: success, ok: true }),
        onError: (errors) => (message.value = { text: firstError(errors as FlutterwaveErrors), ok: false }),
        onFinish: () => (busy.value = false),
    });
}

function refund() {
    if (!confirming.value) {
        confirming.value = true;

        return;
    }

    const data = Object.fromEntries(Object.entries({ amount: amount.value, comment: comment.value }).filter(([, value]) => value !== ''));
    post(props.transaction.urls!.refund!, data, amount.value ? `Refund of ${formatMoney(props.transaction.currency, amount.value)} started.` : `Full refund of ${total.value} started.`);
    refunding.value = false;
    confirming.value = false;
}

function cancel() {
    refunding.value = false;
    confirming.value = false;
}
</script>

<template>
    <li>
        <div class="flw-row">
            <div class="flw-stack">
                <span class="flw-amount">{{ total }}</span>
                <span class="flw-meta">
                    <span>{{ transaction.customer_name || transaction.customer_email }}</span>
                    <span v-if="transaction.customer_name && transaction.customer_email">{{ transaction.customer_email }}</span>
                    <span>{{ transaction.tx_ref }}</span>
                    <span>{{ formatDate(transaction.created_at) }}</span>
                    <span v-if="transaction.payment_type">{{ transaction.payment_type.replace(/_/g, ' ') }}</span>
                </span>
            </div>
            <span class="flw-pill" :data-tone="statusTone(transaction.status)">{{ statusLabel(transaction.status) }}</span>
        </div>

        <div class="flw-actions">
            <button type="button" class="flw-link-btn" @click="emit('visit', { transaction: open ? null : String(transaction.id) })">{{ open ? 'Hide timeline' : 'Timeline' }}</button>
            <button v-if="transaction.urls?.refund && !refunding" type="button" class="flw-btn flw-btn--outline flw-btn--sm" @click="refunding = true">Refund</button>
            <button v-if="transaction.urls" type="button" :disabled="busy" class="flw-btn flw-btn--outline flw-btn--sm" @click="post(transaction.urls.resend_webhook, {}, 'Webhook sent again.')">Resend webhook</button>
        </div>

        <p v-if="message" :role="message.ok ? 'status' : 'alert'" class="flw-notice" :class="message.ok ? 'flw-notice--success' : 'flw-notice--error'">{{ message.text }}</p>

        <form v-if="refunding" class="flw-panel" @submit.prevent="refund">
            <label class="flw-field"><span class="flw-field__label">Amount to refund</span><input v-model="amount" inputmode="decimal" :placeholder="`Leave empty to refund ${total} in full`" class="flw-input" @input="confirming = false" /></label>
            <label class="flw-field"><span class="flw-field__label">Reason</span><input v-model="comment" maxlength="255" placeholder="Damaged on delivery" class="flw-input" /></label>
            <p v-if="confirming" class="flw-notice flw-notice--info">Refund {{ amount ? formatMoney(transaction.currency, amount) : `${total} in full` }}? Refunds can’t be undone.</p>
            <div class="flw-actions">
                <button type="submit" :disabled="busy" class="flw-btn flw-btn--primary">{{ confirming ? 'Confirm refund' : 'Refund' }}</button>
                <button type="button" class="flw-link-btn flw-link-btn--quiet" @click="cancel">Cancel</button>
            </div>
        </form>

        <template v-if="open && panel.timeline">
            <p v-if="panel.timeline.error" class="flw-error-text">{{ panel.timeline.error }}</p>
            <ol v-else class="flw-timeline">
                <li v-for="(event, index) in panel.timeline.events" :key="index">{{ event.note ?? event.action }} <span class="flw-muted">{{ formatDate(event.created_at) }}</span></li>
            </ol>
        </template>
    </li>
</template>
