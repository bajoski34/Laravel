<script lang="ts">
    import type { Snippet } from 'svelte';
    import { loadFlutterwave, openCheckout, type CheckoutCallbacks } from './checkout';
    import type { FlutterwaveCheckoutConfig } from './types';

    interface Props extends CheckoutCallbacks {
        /** From Flutterwave::inlineConfig() on the server, so the amount can't be changed in the browser. */
        config: FlutterwaveCheckoutConfig;
        /** Replaces the default classes. */
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

    const defaultClass =
        'inline-flex items-center justify-center rounded-md bg-orange-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-orange-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-500 disabled:cursor-not-allowed disabled:opacity-50';
</script>

<button
    type="button"
    class={className ?? defaultClass}
    disabled={disabled || !ready}
    title={error?.message}
    onclick={() => openCheckout(config, { onSuccess, onClose })}
>
    {#if children}{@render children()}{:else}Pay now{/if}
</button>
