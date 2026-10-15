import { useEffect, useState } from 'react';
import { loadFlutterwave, openCheckout } from './checkout';

export type { CheckoutCallbacks } from './checkout';

export function useFlutterwaveCheckout() {
    const [ready, setReady] = useState(() => typeof window !== 'undefined' && Boolean(window.FlutterwaveCheckout));
    const [error, setError] = useState<Error | null>(null);

    useEffect(() => {
        let active = true;

        loadFlutterwave().then(
            () => active && setReady(true),
            (reason: Error) => active && setError(reason),
        );

        return () => {
            active = false;
        };
    }, []);

    return { open: openCheckout, ready, error };
}
