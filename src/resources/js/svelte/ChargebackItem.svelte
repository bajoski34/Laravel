<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { chargebackStatus, checkProof, firstError, formatDeadline, toneClasses } from './admin';
    import type { FlutterwaveChargeback, FlutterwaveChargebackPanel, FlutterwaveErrors } from './types';

    interface Props {
        chargeback: FlutterwaveChargeback;
        proofRules: FlutterwaveChargebackPanel['proof'];
    }

    let { chargeback, proofRules }: Props = $props();

    let mode = $state<'idle' | 'accept' | 'decline'>('idle');
    let comment = $state('');
    let proof = $state<File | null>(null);
    let error = $state<string | null>(null);
    let done = $state<string | null>(null);
    let busy = $state(false);
    const status = $derived(chargebackStatus(chargeback));
    const urgent = $derived(chargeback.respond_within !== null && chargeback.respond_within < 2 * 3600);

    function pickFile(event: Event) {
        proof = (event.currentTarget as HTMLInputElement).files?.[0] ?? null;
    }

    function cancel() {
        mode = 'idle';
        error = null;
    }

    function submit(event: SubmitEvent) {
        event.preventDefault();
        const urls = chargeback.urls!;
        const accepting = mode === 'accept';
        const problem = accepting ? null : checkProof(proof, proofRules);

        if (!comment.trim() || problem) {
            error = problem ?? (accepting ? 'Say why you’re accepting it.' : 'Say what your evidence shows.');

            return;
        }

        busy = true;
        error = null;
        router.post(accepting ? urls.accept : urls.decline, accepting ? { comment } : { comment, proof }, {
            preserveScroll: true,
            forceFormData: !accepting,
            onSuccess: () => {
                done = accepting ? 'Accepted. The customer will be refunded.' : 'Dispute sent. Flutterwave emails you as it moves on.';
                mode = 'idle';
            },
            onError: (errors) => (error = firstError(errors as FlutterwaveErrors)),
            onFinish: () => (busy = false),
        });
    }
</script>

<li class="grid gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="grid gap-0.5">
            <span class="text-lg font-semibold tabular-nums">{chargeback.amount?.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
            <span class="text-xs text-neutral-500 dark:text-neutral-400">{chargeback.tx_ref} · {chargeback.flw_ref}</span>
        </div>
        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {toneClasses[status.tone]}">{status.label}</span>
    </div>

    {#if chargeback.respond_within !== null}
        <p class="text-sm {urgent ? 'font-medium text-red-700 dark:text-red-400' : 'text-neutral-600 dark:text-neutral-300'}">
            {chargeback.respond_within > 0 ? `Respond within ${formatDeadline(chargeback.respond_within)}, or the customer is refunded automatically.` : 'The response deadline has passed.'}
        </p>
    {/if}

    {#if chargeback.comment}
        <p class="text-sm text-neutral-600 dark:text-neutral-300">Customer’s reason: {chargeback.comment}</p>
    {/if}

    {#if done}
        <p role="status" class="text-sm font-medium text-green-700 dark:text-green-400">{done}</p>
    {/if}

    {#if chargeback.urls && mode === 'idle' && !done}
        <div class="flex flex-wrap gap-2">
            <button type="button" class="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-700 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200" onclick={() => (mode = 'decline')}>Dispute with proof</button>
            <button type="button" class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800" onclick={() => (mode = 'accept')}>Accept and refund</button>
        </div>
    {/if}

    {#if mode !== 'idle'}
        <form class="grid gap-3 rounded-lg bg-neutral-50 p-3 dark:bg-neutral-900" onsubmit={submit}>
            {#if mode === 'decline'}
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Evidence</span>
                    <input type="file" accept={proofRules.types.map((type) => `.${type}`).join(',')} class="text-sm" onchange={pickFile} />
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">One JPEG, PNG or PDF under 8MB. Merge receipts, delivery notes and messages into one file.</span>
                </label>
            {/if}
            <label class="grid gap-1 text-sm">
                <span class="font-medium">{mode === 'accept' ? 'Why are you accepting it?' : 'What does your evidence show?'}</span>
                <textarea bind:value={comment} rows="2" class="rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-950"></textarea>
            </label>
            {#if error}
                <p role="alert" class="text-sm text-red-700 dark:text-red-400">{error}</p>
            {/if}
            <div class="flex flex-wrap gap-2">
                <button type="submit" disabled={busy} class="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-neutral-900">
                    {busy ? 'Sending…' : mode === 'accept' ? 'Accept and refund the customer' : 'Send dispute'}
                </button>
                <button type="button" class="rounded-md px-3 py-1.5 text-sm text-neutral-600 hover:underline dark:text-neutral-300" onclick={cancel}>Cancel</button>
            </div>
        </form>
    {/if}

    {#if chargeback.history.length}
        <details class="text-sm">
            <summary class="cursor-pointer text-neutral-500 dark:text-neutral-400">History</summary>
            <ol class="mt-2 grid gap-1.5 border-l border-neutral-200 pl-3 dark:border-neutral-800">
                {#each chargeback.history as entry, index (index)}
                    <li class="grid">
                        <span>{entry.description}{entry.comment ? `: ${entry.comment}` : ''}</span>
                        <span class="text-xs text-neutral-500 dark:text-neutral-400">{entry.date && new Date(entry.date).toLocaleString()}</span>
                    </li>
                {/each}
            </ol>
        </details>
    {/if}
</li>
