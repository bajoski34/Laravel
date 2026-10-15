<script setup lang="ts">
import { computed, ref } from 'vue';
import { filterBanks } from './admin';
import type { FlutterwaveBank } from './types';

/** A dropdown of banks: each option shows the bank's name and its value is the bank code. Use v-model for the code. */
const props = withDefaults(
    defineProps<{
        /** From banks()->options($country) on the server. */
        banks: FlutterwaveBank[];
        /** The form field name; it submits the bank's code. */
        name?: string;
        /** Show a search box. Defaults to on when there are more than 12 banks. */
        searchable?: boolean;
        required?: boolean;
        disabled?: boolean;
        placeholder?: string;
        id?: string;
    }>(),
    { name: 'account_bank', searchable: undefined, required: true, disabled: false, placeholder: 'Choose a bank' },
);

const model = defineModel<string>({ default: '' });
const emit = defineEmits<{ select: [bank: FlutterwaveBank | undefined] }>();
const term = ref('');
const visible = computed(() => filterBanks(props.banks, term.value, model.value));
const showSearch = computed(() => props.searchable ?? props.banks.length > 12);

function pick(event: Event) {
    model.value = (event.target as HTMLSelectElement).value;
    emit('select', props.banks.find((bank) => bank.code === model.value));
}
</script>

<template>
    <div class="flw-bank-select">
        <input v-if="showSearch" v-model="term" type="search" class="flw-input" :placeholder="`Search ${banks.length} banks`" aria-label="Search banks" autocomplete="off" />
        <div class="flw-select">
            <select :id="id" :name="name" :value="model" :required="required" :disabled="disabled || banks.length === 0" class="flw-input" @change="pick">
                <option value="">{{ placeholder }}</option>
                <option v-for="bank in visible" :key="bank.code" :value="bank.code">{{ bank.name }}</option>
            </select>
            <span class="flw-select__chevron" aria-hidden="true"><svg viewBox="0 0 16 16" width="16" height="16"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
        </div>
    </div>
</template>
