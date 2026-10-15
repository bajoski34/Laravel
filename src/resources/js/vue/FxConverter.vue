<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { firstError, formatMoney, fxStartOverUrl } from './admin';
import { formatDuration, startCountdown, startPolling } from './format';
import type { FlutterwaveErrors, FlutterwaveFxPanel } from './types';

const props = withDefaults(
    defineProps<{
        /** From fx()->panel($request->query('fx_quote'), $request->query('fx_trade')) on the server. */
        panel: FlutterwaveFxPanel;
        /** The prop name you passed the panel as, so checking for updates only reloads it. Defaults to all props. */
        reloadOnly?: string[];
        /** How often to check a pending quote or trade, in milliseconds. */
        pollInterval?: number;
    }>(),
    { pollInterval: 3000 },
);

const primary = 'rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700 disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200';
const quiet = 'text-sm text-neutral-600 underline-offset-2 hover:underline dark:text-neutral-300';
const input = 'rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-950';

const quote = computed(() => props.panel.quote);
const trade = computed(() => props.panel.trade);
const waiting = computed(() => Boolean(trade.value?.pending || (!trade.value && quote.value?.pending)));

// Request step
const pair = ref(props.panel.pairs[0]?.join('/') ?? 'NGN/USD');
const quantity = ref('');
const base = computed(() => pair.value.split('/')[0]);
const target = computed(() => pair.value.split('/')[1]);

// Quote step
const narration = ref('');
const confirming = ref(false);
const error = ref<string | null>(null);
const busy = ref(false);
const secondsLeft = ref<number | null>(props.panel.quote?.expires_in ?? null);

const expired = computed(() => quote.value?.status === 'EXPIRED' || (quote.value?.status === 'READY' && secondsLeft.value === 0));
const sell = computed(() => formatMoney(quote.value?.base ?? null, quote.value?.approved_quantity ?? null));
const get = computed(() => formatMoney(quote.value?.target ?? null, quote.value?.total_value ?? null));
const trimmed = computed(() => quote.value?.quantity != null && quote.value?.approved_quantity != null && quote.value.approved_quantity < quote.value.quantity);

// Timers start once mounted, so server-side rendering never touches window.
let stopCountdown = () => {};
let stopPolling = () => {};

function restartCountdown() {
    stopCountdown();
    stopCountdown = () => {};
    secondsLeft.value = quote.value?.expires_in ?? null;

    if (quote.value?.expires_in != null) {
        stopCountdown = startCountdown(quote.value.expires_in, (left) => (secondsLeft.value = left));
    }
}

function restartPolling() {
    stopPolling();
    stopPolling = () => {};

    if (waiting.value && props.pollInterval) {
        stopPolling = startPolling(() => router.reload(props.reloadOnly ? { only: props.reloadOnly } : {}), props.pollInterval);
    }
}

onMounted(() => {
    restartCountdown();
    restartPolling();
});
watch(() => [quote.value?.id, quote.value?.expires_in], restartCountdown);
watch(() => [waiting.value, props.pollInterval, props.reloadOnly?.join(',')], restartPolling);
onBeforeUnmount(() => {
    stopCountdown();
    stopPolling();
});

function startOver() {
    router.visit(fxStartOverUrl());
}

function requestQuote() {
    if (!(Number(quantity.value) > 0)) {
        error.value = `Enter how much ${base.value} to sell.`;

        return;
    }

    busy.value = true;
    error.value = null;
    router.post(props.panel.urls!.quote, { base: base.value, target: target.value, quantity: quantity.value }, {
        preserveScroll: true,
        onError: (errors) => (error.value = firstError(errors as FlutterwaveErrors)),
        onFinish: () => (busy.value = false),
    });
}

function tradeQuote() {
    if (!narration.value.trim()) {
        error.value = 'Add a note for your records, e.g. Supplier payment.';

        return;
    }

    if (!confirming.value) {
        error.value = null;
        confirming.value = true;

        return;
    }

    busy.value = true;
    router.post(props.panel.urls!.trade, { quote_id: quote.value!.id, narration: narration.value }, {
        preserveScroll: true,
        onError: (errors) => {
            error.value = firstError(errors as FlutterwaveErrors);
            confirming.value = false;
        },
        onFinish: () => (busy.value = false),
    });
}
</script>

<template>
    <div class="grid max-w-md gap-4 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
        <p v-if="panel.error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ panel.error }}</p>

        <div v-if="trade" class="grid gap-3" aria-live="polite">
            <h3 class="text-base font-semibold">{{ trade.status === 'SETTLED' ? 'Trade settled' : trade.status === 'FAILED' ? 'Trade failed' : 'Trade in progress…' }}</h3>
            <p class="text-sm" :class="trade.status === 'FAILED' ? 'text-red-700 dark:text-red-400' : 'text-neutral-600 dark:text-neutral-300'">
                <template v-if="trade.status === 'SETTLED'">Sold {{ formatMoney(trade.base, trade.sold) }} for {{ formatMoney(trade.target, trade.bought) }}{{ trade.recipient ? `, paid to ${trade.recipient}` : '' }}.</template>
                <template v-else-if="trade.status === 'FAILED'">{{ trade.message ?? 'Flutterwave couldn’t complete the trade.' }}</template>
                <template v-else>Selling {{ formatMoney(trade.base, trade.sold) }} for {{ formatMoney(trade.target, trade.bought) }}. This updates by itself.</template>
            </p>
            <p class="text-xs text-neutral-500 dark:text-neutral-400">Reference {{ trade.reference ?? trade.id }}</p>
            <button v-if="!trade.pending" type="button" :class="[primary, 'w-fit']" @click="startOver">Start another conversion</button>
        </div>

        <div v-else-if="quote && quote.pending" class="grid gap-2" aria-live="polite">
            <h3 class="text-base font-semibold">Getting a rate…</h3>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">Flutterwave usually prices a quote within a few seconds. This updates by itself.</p>
        </div>

        <div v-else-if="quote && (!quote.tradable || expired)" class="grid gap-3" aria-live="polite">
            <h3 class="text-base font-semibold">{{ expired ? 'This rate has expired' : 'No rate available' }}</h3>
            <p class="text-sm text-neutral-600 dark:text-neutral-300">{{ expired ? 'Rates are held for 5 minutes. Get a new one to continue.' : quote.message }}</p>
            <button type="button" :class="[primary, 'w-fit']" @click="startOver">Get a new rate</button>
        </div>

        <form v-else-if="quote" class="grid gap-4" @submit.prevent="tradeQuote">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-base font-semibold">Your rate</h3>
                <span v-if="secondsLeft !== null" class="text-sm tabular-nums text-neutral-500 dark:text-neutral-400">Held for {{ formatDuration(secondsLeft) }}</span>
            </div>
            <dl class="grid grid-cols-2 gap-3">
                <div><dt class="text-xs text-neutral-500 dark:text-neutral-400">You sell</dt><dd class="font-semibold tabular-nums">{{ sell }}</dd></div>
                <div><dt class="text-xs text-neutral-500 dark:text-neutral-400">You get</dt><dd class="font-semibold tabular-nums">{{ get }}</dd></div>
                <div class="col-span-2"><dt class="text-xs text-neutral-500 dark:text-neutral-400">Rate</dt><dd class="tabular-nums">1 {{ quote.base }} = {{ quote.rate }} {{ quote.target }}</dd></div>
            </dl>
            <p v-if="trimmed" class="rounded-md bg-amber-50 p-2 text-sm text-amber-900 dark:bg-amber-400/10 dark:text-amber-300">Flutterwave approved {{ sell }} of the {{ formatMoney(quote.base, quote.quantity) }} you asked for.</p>
            <template v-if="panel.urls">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Note for your records</span>
                    <input v-model="narration" placeholder="Supplier payment" :class="input" @input="confirming = false" />
                </label>
                <p v-if="error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
                <p v-if="confirming" class="text-sm font-medium">Sell {{ sell }} for {{ get }}? Trades can’t be undone.</p>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" :disabled="busy" :class="primary">{{ busy ? 'Trading…' : confirming ? 'Confirm trade' : 'Trade at this rate' }}</button>
                    <button type="button" :class="quiet" @click="confirming ? (confirming = false) : startOver()">{{ confirming ? 'Back' : 'Get a different rate' }}</button>
                </div>
            </template>
        </form>

        <p v-else-if="!panel.urls" class="text-sm text-neutral-500 dark:text-neutral-400">Add Route::flutterwaveAdmin() to your routes to request quotes here.</p>

        <form v-else class="grid gap-4" @submit.prevent="requestQuote">
            <div class="grid gap-1">
                <h3 class="text-base font-semibold">Convert currency</h3>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Get a rate, then trade it within 5 minutes. Weekdays only, from USD 1,000 or its equivalent.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-[auto_1fr]">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Convert</span>
                    <select v-model="pair" :class="input">
                        <option v-for="[from, to] in panel.pairs" :key="`${from}/${to}`" :value="`${from}/${to}`">{{ from }} to {{ to }}</option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Amount of {{ base }} to sell</span>
                    <input v-model="quantity" inputmode="decimal" placeholder="2000000" :class="[input, 'tabular-nums']" />
                </label>
            </div>
            <p v-if="error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
            <button type="submit" :disabled="busy" :class="[primary, 'w-fit']">{{ busy ? 'Requesting…' : `Get a ${base} to ${target} rate` }}</button>
        </form>
    </div>
</template>
