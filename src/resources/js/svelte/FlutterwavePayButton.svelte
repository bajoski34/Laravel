<script lang="ts">
    import type { Snippet } from 'svelte';
    import { loadFlutterwave, openCheckout, type CheckoutCallbacks } from './checkout';
    import type { FlutterwaveCheckoutConfig } from './types';

    interface Props extends CheckoutCallbacks {
        /** From Flutterwave::inlineConfig() on the server, so the amount can't be changed in the browser. */
        config: FlutterwaveCheckoutConfig;
        /** Added to the button. */
        class?: string;
        disabled?: boolean;
        children?: Snippet;
    }

    let { config, onSuccess, onClose, class: className, disabled = false, children }: Props = $props();

    let ready = $state(typeof window !== 'undefined' && Boolean(window.FlutterwaveCheckout));
    let error = $state<Error | null>(null);

    $effect(() => {
        loadFlutterwave().then(
            () => (ready = true),
            (reason: Error) => (error = reason),
        );
    });

</script>

<button type="button" class="flw flw-btn flw-btn--primary{className ? ` ${className}` : ''}" disabled={disabled || !ready} title={error?.message} onclick={() => openCheckout(config, { onSuccess, onClose })}>
    {#if !ready && !error}<span class="flw-btn__spinner" aria-hidden="true"></span>{/if}
    {#if children}{@render children()}{:else}Pay now{/if}
</button>
