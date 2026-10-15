<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { firstError } from './admin';
import BankSelect from './BankSelect.vue';
import Pager from './Pager.vue';
import type { FlutterwaveBeneficiariesPanel, FlutterwaveErrors } from './types';

/** Saved transfer recipients, from beneficiaries()->panel($request->query()). */
const props = defineProps<{ panel: FlutterwaveBeneficiariesPanel; reloadOnly?: string[] }>();

const adding = ref(props.panel.items.length === 0);
const form = reactive({ account_bank: '', account_number: '', beneficiary_name: '' });
const message = ref<{ text: string; ok: boolean } | null>(null);
const busy = ref(false);
const deleting = ref<number | null>(null);

function options(text: string, onSuccess?: () => void) {
    return {
        preserveScroll: true,
        onSuccess: () => {
            message.value = { text, ok: true };
            onSuccess?.();
        },
        onError: (errors: Record<string, string>) => (message.value = { text: firstError(errors as FlutterwaveErrors), ok: false }),
        onFinish: () => (busy.value = false),
        ...(props.reloadOnly ? { only: props.reloadOnly } : {}),
    };
}

function save() {
    busy.value = true;
    router.post(props.panel.urls!.create, { ...form }, options('Beneficiary saved.', () => {
        Object.assign(form, { account_bank: '', account_number: '', beneficiary_name: '' });
        adding.value = false;
    }));
}

function remove(url: string) {
    busy.value = true;
    router.delete(url, options('Beneficiary deleted.'));
    deleting.value = null;
}
</script>

<template>
    <div class="flw">
        <p v-if="message" :role="message.ok ? 'status' : 'alert'" class="flw-notice" :class="message.ok ? 'flw-notice--success' : 'flw-notice--error'">{{ message.text }}</p>

        <template v-if="panel.urls">
            <form v-if="adding" class="flw-card flw-card--narrow" @submit.prevent="save">
                <h3 class="flw-title">Add a beneficiary</h3>
                <hr class="flw-rule" />
                <div class="flw-field">
                    <span class="flw-field__label">Bank</span>
                    <BankSelect v-model="form.account_bank" :banks="panel.banks" />
                    <span v-if="panel.banks_error" class="flw-field__error">{{ panel.banks_error }}</span>
                </div>
                <label class="flw-field"><span class="flw-field__label">Account number</span><input v-model.trim="form.account_number" required inputmode="numeric" maxlength="20" class="flw-input flw-num" /></label>
                <label class="flw-field">
                    <span class="flw-field__label">Name</span>
                    <input v-model="form.beneficiary_name" maxlength="100" class="flw-input" />
                    <span class="flw-field__hint">Leave empty to use the name on the account.</span>
                </label>
                <div class="flw-actions">
                    <button type="submit" :disabled="busy" class="flw-btn flw-btn--primary">
                        <span v-if="busy" class="flw-btn__spinner" aria-hidden="true" />
                        {{ busy ? 'Saving' : 'Save beneficiary' }}
                    </button>
                    <button v-if="panel.items.length" type="button" class="flw-link-btn flw-link-btn--quiet" @click="adding = false">Cancel</button>
                </div>
            </form>
            <button v-else type="button" class="flw-btn flw-btn--outline flw-btn--sm" @click="adding = true">Add a beneficiary</button>
        </template>

        <p v-if="panel.error" class="flw-notice flw-notice--error">Couldn’t load beneficiaries: {{ panel.error }}</p>
        <p v-else-if="panel.items.length === 0" class="flw-text">No beneficiaries yet. Saved recipients make repeat transfers quicker.</p>
        <ul v-else class="flw-rows">
            <li v-for="beneficiary in panel.items" :key="beneficiary.id">
                <div class="flw-row" style="align-items: center">
                    <div class="flw-stack">
                        <span class="flw-strong">{{ beneficiary.full_name }}</span>
                        <span class="flw-meta"><span>{{ beneficiary.account_number }}</span><span>{{ beneficiary.bank_name }} ({{ beneficiary.bank_code }})</span></span>
                    </div>
                    <template v-if="beneficiary.urls">
                        <span v-if="deleting === beneficiary.id" class="flw-inline">
                            <span>Delete {{ beneficiary.full_name }}?</span>
                            <button type="button" :disabled="busy" class="flw-btn flw-btn--danger flw-btn--sm" @click="remove(beneficiary.urls.delete)">Delete</button>
                            <button type="button" class="flw-link-btn flw-link-btn--quiet" @click="deleting = null">Keep</button>
                        </span>
                        <button v-else type="button" class="flw-btn flw-btn--outline flw-btn--sm" @click="deleting = beneficiary.id">Delete</button>
                    </template>
                </div>
            </li>
        </ul>

        <Pager :page="panel.page" :reload-only="reloadOnly" />
    </div>
</template>
