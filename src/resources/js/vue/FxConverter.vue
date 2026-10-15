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
    <div class="flw flw-card flw-card--narrow">
        <p v-if="panel.error" role="alert" class="flw-notice flw-notice--error">{{ panel.error }}</p>

        <div v-if="trade" class="flw-stack" style="gap: 14px" aria-live="polite">
            <h3 class="flw-title">{{ trade.status === 'SETTLED' ? 'Trade settled' : trade.status === 'FAILED' ? 'Trade failed' : 'Trade in progress' }}</h3>
            <hr class="flw-rule" />
            <p v-if="trade.status === 'SETTLED'" class="flw-text">Sold {{ formatMoney(trade.base, trade.sold) }} for <strong>{{ formatMoney(trade.target, trade.bought) }}</strong>{{ trade.recipient ? `, paid to ${trade.recipient}` : '' }}.</p>
            <p v-else-if="trade.status === 'FAILED'" class="flw-notice flw-notice--error">{{ trade.message ?? 'Flutterwave couldn’t complete the trade.' }}</p>
            <p v-else class="flw-countdown"><span class="flw-ring" aria-hidden="true" />Selling {{ formatMoney(trade.base, trade.sold) }} for {{ formatMoney(trade.target, trade.bought) }}. This updates by itself.</p>
            <p class="flw-muted">Reference {{ trade.reference ?? trade.id }}</p>
            <button v-if="!trade.pending" type="button" class="flw-btn flw-btn--primary flw-btn--block" @click="startOver">Start another conversion</button>
        </div>

        <div v-else-if="quote && quote.pending" class="flw-stack" style="gap: 10px" aria-live="polite">
            <h3 class="flw-title">Getting a rate</h3>
            <hr class="flw-rule" />
            <p class="flw-countdown"><span class="flw-ring" aria-hidden="true" />Flutterwave usually prices a quote within a few seconds. This updates by itself.</p>
        </div>

        <div v-else-if="quote && (!quote.tradable || expired)" class="flw-stack" style="gap: 14px" aria-live="polite">
            <h3 class="flw-title">{{ expired ? 'This rate has expired' : 'No rate available' }}</h3>
            <hr class="flw-rule" />
            <p class="flw-text">{{ expired ? 'Rates are held for 5 minutes. Get a new one to continue.' : quote.message }}</p>
            <button type="button" class="flw-btn flw-btn--primary flw-btn--block" @click="startOver">Get a new rate</button>
        </div>

        <form v-else-if="quote" class="flw-stack" style="gap: 16px" @submit.prevent="tradeQuote">
            <div class="flw-row">
                <h3 class="flw-title">Your rate</h3>
                <p v-if="secondsLeft !== null" class="flw-countdown">Held for <strong>{{ formatDuration(secondsLeft) }}</strong></p>
            </div>
            <hr class="flw-rule" />
            <dl class="flw-dl">
                <div><dt>You sell</dt><dd>{{ sell }}</dd></div>
                <div><dt>You get</dt><dd>{{ get }}</dd></div>
                <div><dt>Rate</dt><dd>1 {{ quote.base }} = {{ quote.rate }} {{ quote.target }}</dd></div>
            </dl>
            <p v-if="trimmed" class="flw-notice flw-notice--warning">Flutterwave approved {{ sell }} of the {{ formatMoney(quote.base, quote.quantity) }} you asked for.</p>
            <template v-if="panel.urls">
                <label class="flw-field" :class="{ 'has-error': error }">
                    <span class="flw-field__label">Note for your records</span>
                    <input v-model="narration" placeholder="Supplier payment" class="flw-input" @input="confirming = false" />
                    <span v-if="error" role="alert" class="flw-field__error">{{ error }}</span>
                </label>
                <p v-if="confirming" class="flw-notice flw-notice--info">Sell {{ sell }} for {{ get }}? Trades can’t be undone.</p>
                <button type="submit" :disabled="busy" class="flw-btn flw-btn--primary flw-btn--block">
                    <span v-if="busy" class="flw-btn__spinner" aria-hidden="true" />
                    {{ busy ? 'Trading' : confirming ? 'Confirm trade' : 'Trade at this rate' }}
                </button>
                <button type="button" class="flw-link-btn flw-link-btn--quiet" @click="confirming ? (confirming = false) : startOver()">{{ confirming ? 'Back' : 'Get a different rate' }}</button>
            </template>
        </form>

        <p v-else-if="!panel.urls" class="flw-text">Add Route::flutterwaveAdmin() to your routes to request quotes here.</p>

        <form v-else class="flw-stack" style="gap: 16px" @submit.prevent="requestQuote">
            <div class="flw-stack">
                <h3 class="flw-title">Convert currency</h3>
                <p class="flw-text">Get a rate, then trade it within 5 minutes. Weekdays only, from USD 1,000 or its equivalent.</p>
            </div>
            <hr class="flw-rule" />
            <label class="flw-field">
                <span class="flw-field__label">Convert</span>
                <span class="flw-select">
                    <select v-model="pair" class="flw-input">
                        <option v-for="[from, to] in panel.pairs" :key="`${from}/${to}`" :value="`${from}/${to}`">{{ from }} to {{ to }}</option>
                    </select>
                    <span class="flw-select__chevron" aria-hidden="true"><svg viewBox="0 0 16 16" width="16" height="16"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
                </span>
            </label>
            <label class="flw-field" :class="{ 'has-error': error }">
                <span class="flw-field__label">Amount of {{ base }} to sell</span>
                <input v-model="quantity" inputmode="decimal" placeholder="2000000" class="flw-input flw-num" />
                <span v-if="error" role="alert" class="flw-field__error">{{ error }}</span>
            </label>
            <button type="submit" :disabled="busy" class="flw-btn flw-btn--primary flw-btn--block">
                <span v-if="busy" class="flw-btn__spinner" aria-hidden="true" />
                {{ busy ? 'Requesting' : `Get a ${base} to ${target} rate` }}
            </button>
        </form>
    </div>
</template>
