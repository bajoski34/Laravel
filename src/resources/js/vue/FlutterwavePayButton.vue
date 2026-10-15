<script setup lang="ts">
import type { FlutterwavePayment, FlutterwaveCheckoutConfig } from './types';
import { useFlutterwaveCheckout } from './useFlutterwaveCheckout';

const props = defineProps<{
    /** From Flutterwave::inlineConfig() on the server, so the amount can't be changed in the browser. */
    config: FlutterwaveCheckoutConfig;
    disabled?: boolean;
    /**
     * Called with the payment while the modal is open (or listen with @success). Passing this skips the config's
     * redirect_url, because Flutterwave ignores callbacks when one is set. Verify on your server.
     */
    onSuccess?: (payment: FlutterwavePayment) => void;
    /** Called when the customer closes the modal (or @close). `incomplete` is true if they didn't pay. */
    onClose?: (incomplete: boolean) => void;
}>();

const { open, ready, error } = useFlutterwaveCheckout();

</script>

<template>
    <button type="button" class="flw flw-btn flw-btn--primary" :disabled="props.disabled || !ready" :title="error?.message" @click="open(props.config, { onSuccess: props.onSuccess, onClose: props.onClose })">
        <span v-if="!ready && !error" class="flw-btn__spinner" aria-hidden="true" />
        <slot>Pay now</slot>
    </button>
</template>
