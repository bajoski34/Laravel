<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { copyText, formatAmount, formatDuration, startCountdown, startPolling } from './format';
import type { FlutterwaveVirtualAccount } from './types';

const props = withDefaults(
    defineProps<{
        /** From virtualAccounts()->dynamicFor() on the server. */
        account: FlutterwaveVirtualAccount;
        /**
         * Whether the order is paid, from your own props. Your ChargeCompleted listener
         * marks the order paid when the webhook arrives; while this is false the
         * component reloads the props below so the page notices.
         */
        paid?: boolean;
        /** Props to refresh while waiting, e.g. ['order']. Defaults to all props. */
        reloadOnly?: string[];
        /** How often to check for the payment, in milliseconds. 0 turns checking off. */
        pollInterval?: number;
        paidMessage?: string;
        expiredMessage?: string;
    }>(),
    {
        paid: false,
        pollInterval: 5000,
        paidMessage: 'Payment received. Thank you!',
        expiredMessage: 'This account has expired. Refresh the page to get a new one.',
    },
);

const emit = defineEmits<{ paid: []; expired: [] }>();

const secondsLeft = ref<number | null>(props.account.expires_in ?? null);
const expired = computed(() => secondsLeft.value === 0);
const copyLabel = ref('Copy');
const numberEl = ref<HTMLElement | null>(null);
const amount = computed(() => Number(props.account.amount ?? 0));

// Timers only start once mounted, so server-side rendering never touches window.
let stopCountdown = () => {};
function restartCountdown() {
    stopCountdown();
    stopCountdown = () => {};
    secondsLeft.value = props.account.expires_in ?? null;

    if (props.account.expires_in !== undefined) {
        stopCountdown = startCountdown(props.account.expires_in, (left) => (secondsLeft.value = left));
    }
}

let stopPolling = () => {};
function restartPolling() {
    stopPolling();
    stopPolling = () => {};

    if (!props.paid && !expired.value && props.pollInterval) {
        stopPolling = startPolling(() => router.reload(props.reloadOnly ? { only: props.reloadOnly } : {}), props.pollInterval);
    }
}

onMounted(() => {
    restartCountdown();
    restartPolling();
});

watch(() => [props.account.account_number, props.account.expires_in], restartCountdown);
watch(() => [props.paid, expired.value, props.pollInterval, props.reloadOnly?.join(',')], restartPolling);
watch(
    () => props.paid,
    (paid) => paid && emit('paid'),
    { immediate: true },
);
watch(expired, (isExpired) => isExpired && !props.paid && emit('expired'));

onBeforeUnmount(() => {
    stopCountdown();
    stopPolling();
});

async function copy() {
    copyLabel.value = (await copyText(props.account.account_number, numberEl.value)) ? 'Copied' : 'Selected';
    window.setTimeout(() => (copyLabel.value = 'Copy'), 2000);
}
</script>

<template>
    <div class="flw flw-card flw-card--narrow flw-account" :class="{ 'is-expired': expired && !paid }">
        <div class="flw-stack">
            <h3 class="flw-title">Pay by bank transfer</h3>
            <p class="flw-text">Send the exact amount to this account from your banking app.</p>
        </div>
        <hr class="flw-rule" />

        <div class="flw-account__details flw-stack" style="gap: 16px">
            <div class="flw-field">
                <span class="flw-field__label">Account number</span>
                <div class="flw-copyfield flw-account__number">
                    <input ref="numberEl" class="flw-input" readonly :value="account.account_number" aria-label="Account number" />
                    <button type="button" :disabled="expired || paid" class="flw-copyfield__btn" @click="copy">{{ copyLabel }}</button>
                </div>
            </div>
            <dl class="flw-dl">
                <div><dt>Bank</dt><dd>{{ account.bank_name }}</dd></div>
                <div v-if="amount > 0"><dt>Amount to send</dt><dd>{{ account.currency ?? 'NGN' }} {{ formatAmount(amount) }}</dd></div>
            </dl>
            <p v-if="account.note" class="flw-muted">{{ account.note }}</p>
        </div>

        <slot />

        <div aria-live="polite">
            <p v-if="paid" class="flw-notice flw-notice--success"><slot name="paid">{{ paidMessage }}</slot></p>
            <p v-else-if="expired" class="flw-notice flw-notice--error"><slot name="expired">{{ expiredMessage }}</slot></p>
            <p v-else-if="secondsLeft !== null" class="flw-countdown">
                <span v-if="pollInterval > 0" class="flw-ring" aria-hidden="true" />
                Expires in <strong>{{ formatDuration(secondsLeft) }}</strong>
                <template v-if="pollInterval > 0"> · We’ll update this page once your transfer arrives.</template>
            </p>
        </div>
    </div>
</template>
