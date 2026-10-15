import type { ButtonHTMLAttributes } from 'react';
import type { FlutterwaveCheckoutConfig } from './types';
import { type CheckoutCallbacks, useFlutterwaveCheckout } from './use-flutterwave-checkout';

type Props = Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'onClick'> &
    CheckoutCallbacks & {
        /** From Flutterwave::inlineConfig() on the server, so the amount can't be changed in the browser. */
        config: FlutterwaveCheckoutConfig;
    };

/** Flutterwave's orange primary button, opening the inline checkout. */
export function FlutterwavePayButton({ config, onSuccess, onClose, children = 'Pay now', className, disabled, ...props }: Props) {
    const { open, ready, error } = useFlutterwaveCheckout();

    return (
        <button
            type="button"
            {...props}
            className={`flw flw-btn flw-btn--primary${className ? ` ${className}` : ''}`}
            disabled={disabled || !ready}
            title={error?.message ?? props.title}
            onClick={() => open(config, { onSuccess, onClose })}
        >
            {!ready && !error && <span className="flw-btn__spinner" aria-hidden="true" />}
            {children}
        </button>
    );
}
