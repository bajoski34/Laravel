<script lang="ts">
    import { filterBanks } from './admin';
    import type { FlutterwaveBank } from './types';

    /** A dropdown of banks: each option shows the bank's name and its value is the bank code. Use bind:value for the code. */
    interface Props {
        /** From banks()->options($country) on the server. */
        banks: FlutterwaveBank[];
        /** The bank code; bindable. */
        value?: string;
        /** The form field name; it submits the bank's code. */
        name?: string;
        /** Show a search box. Defaults to on when there are more than 12 banks. */
        searchable?: boolean;
        required?: boolean;
        disabled?: boolean;
        placeholder?: string;
        id?: string;
        onSelect?: (bank: FlutterwaveBank | undefined) => void;
        /** Added to the wrapper. */
        class?: string;
    }

    let { banks, value = $bindable(''), name = 'account_bank', searchable, required = true, disabled = false, placeholder = 'Choose a bank', id, onSelect, class: className }: Props = $props();

    let term = $state('');
    const visible = $derived(filterBanks(banks, term, value));
    const showSearch = $derived(searchable ?? banks.length > 12);

    function pick(event: Event) {
        value = (event.currentTarget as HTMLSelectElement).value;
        onSelect?.(banks.find((bank) => bank.code === value));
    }
</script>

<div class="flw-bank-select{className ? ` ${className}` : ''}">
    {#if showSearch}
        <input type="search" class="flw-input" bind:value={term} placeholder="Search {banks.length} banks" aria-label="Search banks" autocomplete="off" />
    {/if}
    <div class="flw-select">
        <select {id} {name} {value} {required} disabled={disabled || banks.length === 0} onchange={pick} class="flw-input">
            <option value="">{placeholder}</option>
            {#each visible as bank (bank.code)}
                <option value={bank.code}>{bank.name}</option>
            {/each}
        </select>
        <span class="flw-select__chevron" aria-hidden="true"><svg viewBox="0 0 16 16" width="16" height="16"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
    </div>
</div>
