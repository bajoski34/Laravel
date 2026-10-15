import { onMounted, ref } from 'vue';
import { loadFlutterwave, openCheckout } from './checkout';

export function useFlutterwaveCheckout() {
    const ready = ref(typeof window !== 'undefined' && Boolean(window.FlutterwaveCheckout));
    const error = ref<Error | null>(null);

    onMounted(() => {
        loadFlutterwave().then(
            () => (ready.value = true),
            (reason: Error) => (error.value = reason),
        );
    });

    return { open: openCheckout, ready, error };
}
