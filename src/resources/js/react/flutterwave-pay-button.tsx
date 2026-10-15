import type { ButtonHTMLAttributes } from 'react';
import type { FlutterwaveCheckoutConfig } from './types';
import { type CheckoutCallbacks, useFlutterwaveCheckout } from './use-flutterwave-checkout';

type Props = Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'onClick'> &
    CheckoutCallbacks & {
        /** From Flutterwave::inlineConfig() on the server, so the amount can't be changed in the browser. */
        config: FlutterwaveCheckoutConfig;
    };

const defaultClassName =
    'inline-flex items-center justify-center rounded-md bg-orange-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-orange-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-500 disabled:cursor-not-allowed disabled:opacity-50';

export function FlutterwavePayButton({ config, onSuccess, onClose, children = 'Pay now', className, disabled, ...props }: Props) {
    const { open, ready, error } = useFlutterwaveCheckout();

    return (
        <button
            type="button"
            {...props}
            className={className ?? defaultClassName}
            disabled={disabled || !ready}
            title={error?.message ?? props.title}
            onClick={() => open(config, { onSuccess, onClose })}
        >
            {children}
        </button>
    );
}
