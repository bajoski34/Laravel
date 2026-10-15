<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { chargebackStatus, checkProof, firstError, formatDeadline } from './admin';
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

<li>
    <div class="flw-row">
        <div class="flw-stack">
            <span class="flw-amount">{chargeback.amount?.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
            <span class="flw-meta"><span>{chargeback.tx_ref}</span><span>{chargeback.flw_ref}</span></span>
        </div>
        <span class="flw-pill" data-tone={status.tone}>{status.label}</span>
    </div>

    {#if chargeback.respond_within !== null}
        <p class={urgent ? 'flw-urgent' : 'flw-text'}>
            {chargeback.respond_within > 0 ? `Respond within ${formatDeadline(chargeback.respond_within)}, or the customer is refunded automatically.` : 'The response deadline has passed.'}
        </p>
    {/if}

    {#if chargeback.comment}<p class="flw-text">Customer’s reason: {chargeback.comment}</p>{/if}

    {#if done}<p role="status" class="flw-notice flw-notice--success">{done}</p>{/if}

    {#if chargeback.urls && mode === 'idle' && !done}
        <div class="flw-actions">
            <button type="button" class="flw-btn flw-btn--primary flw-btn--sm" onclick={() => (mode = 'decline')}>Dispute with proof</button>
            <button type="button" class="flw-btn flw-btn--outline flw-btn--sm" onclick={() => (mode = 'accept')}>Accept and refund</button>
        </div>
    {/if}

    {#if mode !== 'idle'}
        <form class="flw-panel" onsubmit={submit}>
            {#if mode === 'decline'}
                <label class="flw-field">
                    <span class="flw-field__label">Evidence</span>
                    <input type="file" class="flw-input" accept={proofRules.types.map((type) => `.${type}`).join(',')} onchange={pickFile} />
                    <span class="flw-field__hint">One JPEG, PNG or PDF under 8MB. Merge receipts, delivery notes and messages into one file.</span>
                </label>
            {/if}
            <label class="flw-field{error ? ' has-error' : ''}">
                <span class="flw-field__label">{mode === 'accept' ? 'Why are you accepting it?' : 'What does your evidence show?'}</span>
                <textarea bind:value={comment} rows="2" class="flw-input"></textarea>
                {#if error}<span role="alert" class="flw-field__error">{error}</span>{/if}
            </label>
            <div class="flw-actions">
                <button type="submit" disabled={busy} class="flw-btn {mode === 'accept' ? 'flw-btn--secondary' : 'flw-btn--primary'}">
                    {#if busy}<span class="flw-btn__spinner" aria-hidden="true"></span>{/if}
                    {busy ? 'Sending' : mode === 'accept' ? 'Accept and refund the customer' : 'Send dispute'}
                </button>
                <button type="button" class="flw-link-btn flw-link-btn--quiet" onclick={cancel}>Cancel</button>
            </div>
        </form>
    {/if}

    {#if chargeback.history.length}
        <details class="flw-details">
            <summary>History</summary>
            <ol class="flw-timeline">
                {#each chargeback.history as entry, index (index)}
                    <li>{entry.description}{entry.comment ? `: ${entry.comment}` : ''} <span class="flw-muted">{entry.date && new Date(entry.date).toLocaleString()}</span></li>
                {/each}
            </ol>
        </details>
    {/if}
</li>
