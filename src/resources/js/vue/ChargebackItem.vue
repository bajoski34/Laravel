<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { chargebackStatus, checkProof, firstError, formatDeadline } from './admin';
import type { FlutterwaveChargeback, FlutterwaveChargebackPanel, FlutterwaveErrors } from './types';

const props = defineProps<{ chargeback: FlutterwaveChargeback; proofRules: FlutterwaveChargebackPanel['proof'] }>();

const mode = ref<'idle' | 'accept' | 'decline'>('idle');
const comment = ref('');
const proof = ref<File | null>(null);
const error = ref<string | null>(null);
const done = ref<string | null>(null);
const busy = ref(false);
const status = computed(() => chargebackStatus(props.chargeback));
const urgent = computed(() => props.chargeback.respond_within !== null && props.chargeback.respond_within < 2 * 3600);

function pickFile(event: Event) {
    proof.value = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function cancel() {
    mode.value = 'idle';
    error.value = null;
}

function submit() {
    const urls = props.chargeback.urls!;
    const accepting = mode.value === 'accept';
    const problem = accepting ? null : checkProof(proof.value, props.proofRules);

    if (!comment.value.trim() || problem) {
        error.value = problem ?? (accepting ? 'Say why you’re accepting it.' : 'Say what your evidence shows.');

        return;
    }

    busy.value = true;
    error.value = null;
    router.post(accepting ? urls.accept : urls.decline, accepting ? { comment: comment.value } : { comment: comment.value, proof: proof.value }, {
        preserveScroll: true,
        forceFormData: !accepting,
        onSuccess: () => {
            done.value = accepting ? 'Accepted. The customer will be refunded.' : 'Dispute sent. Flutterwave emails you as it moves on.';
            mode.value = 'idle';
        },
        onError: (errors) => (error.value = firstError(errors as FlutterwaveErrors)),
        onFinish: () => (busy.value = false),
    });
}
</script>

<template>
    <li>
        <div class="flw-row">
            <div class="flw-stack">
                <span class="flw-amount">{{ chargeback.amount?.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span>
                <span class="flw-meta"><span>{{ chargeback.tx_ref }}</span><span>{{ chargeback.flw_ref }}</span></span>
            </div>
            <span class="flw-pill" :data-tone="status.tone">{{ status.label }}</span>
        </div>

        <p v-if="chargeback.respond_within !== null" :class="urgent ? 'flw-urgent' : 'flw-text'">
            {{ chargeback.respond_within > 0 ? `Respond within ${formatDeadline(chargeback.respond_within)}, or the customer is refunded automatically.` : 'The response deadline has passed.' }}
        </p>

        <p v-if="chargeback.comment" class="flw-text">Customer’s reason: {{ chargeback.comment }}</p>

        <p v-if="done" role="status" class="flw-notice flw-notice--success">{{ done }}</p>

        <div v-if="chargeback.urls && mode === 'idle' && !done" class="flw-actions">
            <button type="button" class="flw-btn flw-btn--primary flw-btn--sm" @click="mode = 'decline'">Dispute with proof</button>
            <button type="button" class="flw-btn flw-btn--outline flw-btn--sm" @click="mode = 'accept'">Accept and refund</button>
        </div>

        <form v-if="mode !== 'idle'" class="flw-panel" @submit.prevent="submit">
            <label v-if="mode === 'decline'" class="flw-field">
                <span class="flw-field__label">Evidence</span>
                <input type="file" class="flw-input" :accept="proofRules.types.map((type) => `.${type}`).join(',')" @change="pickFile" />
                <span class="flw-field__hint">One JPEG, PNG or PDF under 8MB. Merge receipts, delivery notes and messages into one file.</span>
            </label>
            <label class="flw-field" :class="{ 'has-error': error }">
                <span class="flw-field__label">{{ mode === 'accept' ? 'Why are you accepting it?' : 'What does your evidence show?' }}</span>
                <textarea v-model="comment" rows="2" class="flw-input" />
                <span v-if="error" role="alert" class="flw-field__error">{{ error }}</span>
            </label>
            <div class="flw-actions">
                <button type="submit" :disabled="busy" class="flw-btn" :class="mode === 'accept' ? 'flw-btn--secondary' : 'flw-btn--primary'">
                    <span v-if="busy" class="flw-btn__spinner" aria-hidden="true" />
                    {{ busy ? 'Sending' : mode === 'accept' ? 'Accept and refund the customer' : 'Send dispute' }}
                </button>
                <button type="button" class="flw-link-btn flw-link-btn--quiet" @click="cancel">Cancel</button>
            </div>
        </form>

        <details v-if="chargeback.history.length" class="flw-details">
            <summary>History</summary>
            <ol class="flw-timeline">
                <li v-for="(entry, index) in chargeback.history" :key="index">
                    {{ entry.description }}{{ entry.comment ? `: ${entry.comment}` : '' }} <span class="flw-muted">{{ entry.date && new Date(entry.date).toLocaleString() }}</span>
                </li>
            </ol>
        </details>
    </li>
</template>
