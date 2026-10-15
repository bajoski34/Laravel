<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { firstError } from './admin';
    import BankSelect from './BankSelect.svelte';
    import Pager from './Pager.svelte';
    import type { FlutterwaveBeneficiariesPanel, FlutterwaveErrors } from './types';

    /** Saved transfer recipients, from beneficiaries()->panel($request->query()). */
    let { panel, reloadOnly }: { panel: FlutterwaveBeneficiariesPanel; reloadOnly?: string[] } = $props();

    let opened = $state<boolean | null>(null);
    const adding = $derived(opened ?? panel.items.length === 0);
    let bank = $state('');
    let accountNumber = $state('');
    let name = $state('');
    let message = $state<{ text: string; ok: boolean } | null>(null);
    let busy = $state(false);
    let deleting = $state<number | null>(null);

    function options(text: string, onSuccess?: () => void) {
        return {
            preserveScroll: true,
            onSuccess: () => {
                message = { text, ok: true };
                onSuccess?.();
            },
            onError: (errors: Record<string, string>) => (message = { text: firstError(errors as FlutterwaveErrors), ok: false }),
            onFinish: () => (busy = false),
            ...(reloadOnly ? { only: reloadOnly } : {}),
        };
    }

    function save(event: SubmitEvent) {
        event.preventDefault();
        busy = true;
        router.post(panel.urls!.create, { account_bank: bank, account_number: accountNumber.replace(/\s/g, ''), beneficiary_name: name }, options('Beneficiary saved.', () => {
            bank = '';
            accountNumber = '';
            name = '';
            opened = false;
        }));
    }

    function remove(url: string) {
        busy = true;
        router.delete(url, options('Beneficiary deleted.'));
        deleting = null;
    }
</script>

<div class="flw">
    {#if message}
        <p role={message.ok ? 'status' : 'alert'} class="flw-notice {message.ok ? 'flw-notice--success' : 'flw-notice--error'}">{message.text}</p>
    {/if}

    {#if panel.urls}
        {#if adding}
            <form class="flw-card flw-card--narrow" onsubmit={save}>
                <h3 class="flw-title">Add a beneficiary</h3>
                <hr class="flw-rule" />
                <div class="flw-field">
                    <span class="flw-field__label">Bank</span>
                    <BankSelect banks={panel.banks} bind:value={bank} />
                    {#if panel.banks_error}<span class="flw-field__error">{panel.banks_error}</span>{/if}
                </div>
                <label class="flw-field"><span class="flw-field__label">Account number</span><input required inputmode="numeric" maxlength="20" bind:value={accountNumber} class="flw-input flw-num" /></label>
                <label class="flw-field">
                    <span class="flw-field__label">Name</span>
                    <input maxlength="100" bind:value={name} class="flw-input" />
                    <span class="flw-field__hint">Leave empty to use the name on the account.</span>
                </label>
                <div class="flw-actions">
                    <button type="submit" disabled={busy} class="flw-btn flw-btn--primary">
                        {#if busy}<span class="flw-btn__spinner" aria-hidden="true"></span>{/if}
                        {busy ? 'Saving' : 'Save beneficiary'}
                    </button>
                    {#if panel.items.length}<button type="button" class="flw-link-btn flw-link-btn--quiet" onclick={() => (opened = false)}>Cancel</button>{/if}
                </div>
            </form>
        {:else}
            <button type="button" class="flw-btn flw-btn--outline flw-btn--sm" onclick={() => (opened = true)}>Add a beneficiary</button>
        {/if}
    {/if}

    {#if panel.error}
        <p class="flw-notice flw-notice--error">Couldn’t load beneficiaries: {panel.error}</p>
    {:else if panel.items.length === 0}
        <p class="flw-text">No beneficiaries yet. Saved recipients make repeat transfers quicker.</p>
    {:else}
        <ul class="flw-rows">
            {#each panel.items as beneficiary (beneficiary.id)}
                <li>
                    <div class="flw-row" style="align-items: center">
                        <div class="flw-stack">
                            <span class="flw-strong">{beneficiary.full_name}</span>
                            <span class="flw-meta"><span>{beneficiary.account_number}</span><span>{beneficiary.bank_name} ({beneficiary.bank_code})</span></span>
                        </div>
                        {#if beneficiary.urls}
                            {#if deleting === beneficiary.id}
                                <span class="flw-inline">
                                    <span>Delete {beneficiary.full_name}?</span>
                                    <button type="button" disabled={busy} class="flw-btn flw-btn--danger flw-btn--sm" onclick={() => remove(beneficiary.urls!.delete)}>Delete</button>
                                    <button type="button" class="flw-link-btn flw-link-btn--quiet" onclick={() => (deleting = null)}>Keep</button>
                                </span>
                            {:else}
                                <button type="button" class="flw-btn flw-btn--outline flw-btn--sm" onclick={() => (deleting = beneficiary.id)}>Delete</button>
                            {/if}
                        {/if}
                    </div>
                </li>
            {/each}
        </ul>
    {/if}

    <Pager page={panel.page} {reloadOnly} />
</div>
