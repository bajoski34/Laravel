<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { chargebackStatus, checkProof, firstError, formatDeadline, toneClasses } from './admin';
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
    <li class="grid gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="grid gap-0.5">
                <span class="text-lg font-semibold tabular-nums">{{ chargeback.amount?.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span>
                <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ chargeback.tx_ref }} · {{ chargeback.flw_ref }}</span>
            </div>
            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="toneClasses[status.tone]">{{ status.label }}</span>
        </div>

        <p v-if="chargeback.respond_within !== null" class="text-sm" :class="urgent ? 'font-medium text-red-700 dark:text-red-400' : 'text-neutral-600 dark:text-neutral-300'">
            {{ chargeback.respond_within > 0 ? `Respond within ${formatDeadline(chargeback.respond_within)}, or the customer is refunded automatically.` : 'The response deadline has passed.' }}
        </p>

        <p v-if="chargeback.comment" class="text-sm text-neutral-600 dark:text-neutral-300">Customer’s reason: {{ chargeback.comment }}</p>

        <p v-if="done" role="status" class="text-sm font-medium text-green-700 dark:text-green-400">{{ done }}</p>

        <div v-if="chargeback.urls && mode === 'idle' && !done" class="flex flex-wrap gap-2">
            <button type="button" class="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-700 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200" @click="mode = 'decline'">Dispute with proof</button>
            <button type="button" class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800" @click="mode = 'accept'">Accept and refund</button>
        </div>

        <form v-if="mode !== 'idle'" class="grid gap-3 rounded-lg bg-neutral-50 p-3 dark:bg-neutral-900" @submit.prevent="submit">
            <label v-if="mode === 'decline'" class="grid gap-1 text-sm">
                <span class="font-medium">Evidence</span>
                <input type="file" :accept="proofRules.types.map((type) => `.${type}`).join(',')" class="text-sm" @change="pickFile" />
                <span class="text-xs text-neutral-500 dark:text-neutral-400">One JPEG, PNG or PDF under 8MB. Merge receipts, delivery notes and messages into one file.</span>
            </label>
            <label class="grid gap-1 text-sm">
                <span class="font-medium">{{ mode === 'accept' ? 'Why are you accepting it?' : 'What does your evidence show?' }}</span>
                <textarea v-model="comment" rows="2" class="rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-950" />
            </label>
            <p v-if="error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
            <div class="flex flex-wrap gap-2">
                <button type="submit" :disabled="busy" class="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-neutral-900">
                    {{ busy ? 'Sending…' : mode === 'accept' ? 'Accept and refund the customer' : 'Send dispute' }}
                </button>
                <button type="button" class="rounded-md px-3 py-1.5 text-sm text-neutral-600 hover:underline dark:text-neutral-300" @click="cancel">Cancel</button>
            </div>
        </form>

        <details v-if="chargeback.history.length" class="text-sm">
            <summary class="cursor-pointer text-neutral-500 dark:text-neutral-400">History</summary>
            <ol class="mt-2 grid gap-1.5 border-l border-neutral-200 pl-3 dark:border-neutral-800">
                <li v-for="(entry, index) in chargeback.history" :key="index" class="grid">
                    <span>{{ entry.description }}{{ entry.comment ? `: ${entry.comment}` : '' }}</span>
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ entry.date && new Date(entry.date).toLocaleString() }}</span>
                </li>
            </ol>
        </details>
    </li>
</template>
