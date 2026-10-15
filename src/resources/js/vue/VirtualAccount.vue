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
    <div class="grid max-w-sm gap-4 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
        <dl class="grid gap-3 transition-opacity" :class="{ 'opacity-40': expired && !paid }">
            <div>
                <dt class="text-xs text-neutral-500 dark:text-neutral-400">Bank</dt>
                <dd class="font-semibold">{{ account.bank_name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-neutral-500 dark:text-neutral-400">Account number</dt>
                <dd class="flex items-center gap-2">
                    <span ref="numberEl" class="text-2xl font-semibold tracking-wider tabular-nums">{{ account.account_number }}</span>
                    <button
                        type="button"
                        :disabled="expired || paid"
                        class="rounded-md border border-neutral-300 px-2 py-1 text-xs hover:bg-neutral-100 disabled:opacity-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                        @click="copy"
                    >
                        {{ copyLabel }}
                    </button>
                </dd>
            </div>
            <div v-if="amount > 0">
                <dt class="text-xs text-neutral-500 dark:text-neutral-400">Amount to send</dt>
                <dd class="font-semibold">{{ account.currency ?? 'NGN' }} {{ formatAmount(amount) }}</dd>
            </div>
        </dl>

        <p v-if="account.note" class="text-sm">{{ account.note }}</p>

        <slot />

        <div aria-live="polite" class="text-sm">
            <p v-if="paid" class="font-medium text-green-700 dark:text-green-400">
                <slot name="paid">{{ paidMessage }}</slot>
            </p>
            <p v-else-if="expired" class="text-red-700 dark:text-red-400">
                <slot name="expired">{{ expiredMessage }}</slot>
            </p>
            <p v-else-if="secondsLeft !== null" class="text-neutral-500 dark:text-neutral-400">
                Expires in <span class="tabular-nums">{{ formatDuration(secondsLeft) }}</span>
                <template v-if="pollInterval > 0"> · We’ll update this page once your transfer arrives.</template>
            </p>
        </div>
    </div>
</template>
