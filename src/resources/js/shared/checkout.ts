import type { FlutterwaveCheckoutConfig, FlutterwavePayment } from './types';

const SCRIPT_URL = 'https://checkout.flutterwave.com/v3.js';

let loader: Promise<void> | null = null;

/** Load Flutterwave's checkout script once, however many components ask for it. */
export function loadFlutterwave(): Promise<void> {
    if (typeof window === 'undefined') {
        return Promise.reject(new Error('Flutterwave checkout can only load in the browser.'));
    }

    if (window.FlutterwaveCheckout) {
        return Promise.resolve();
    }

    loader ??= new Promise<void>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT_URL;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => {
            loader = null;
            script.remove();
            reject(new Error('Could not load Flutterwave checkout. Check your connection and try again.'));
        };
        document.head.appendChild(script);
    });

    return loader;
}

export interface CheckoutCallbacks {
    /**
     * Called with the payment while the modal is still open. Passing this skips the
     * config's redirect_url, because Flutterwave ignores the callback when one is set.
     * Verify the payment on your server, e.g. router.post('/orders/1/verify', { transaction_id }).
     */
    onSuccess?: (payment: FlutterwavePayment) => void;
    /** Called when the customer closes the modal. `incomplete` is true if they didn't pay. */
    onClose?: (incomplete: boolean) => void;
}

/** Open the Flutterwave modal, loading the script first if needed. */
export async function openCheckout(config: FlutterwaveCheckoutConfig, { onSuccess, onClose }: CheckoutCallbacks = {}) {
    await loadFlutterwave();

    const { redirect_url: redirectUrl, ...rest } = config;

    const modal = window.FlutterwaveCheckout!({
        ...rest,
        ...(onSuccess ? {} : { redirect_url: redirectUrl }),
        callback: (payment) => {
            modal.close();
            onSuccess?.(payment);
        },
        onclose: (incomplete) => {
            if (onClose) {
                onClose(incomplete);
            } else if (incomplete && redirectUrl) {
                // Same as the Blade button: let the callback route show the cancelled page.
                const separator = redirectUrl.includes('?') ? '&' : '?';
                window.location.href = `${redirectUrl}${separator}status=cancelled&tx_ref=${encodeURIComponent(config.tx_ref)}`;
            }
        },
    });

    return modal;
}
