/** The config from Flutterwave::inlineConfig(), passed to FlutterwaveCheckout(). */
export interface FlutterwaveCheckoutConfig {
    public_key: string;
    tx_ref: string;
    amount: number | string;
    currency?: string;
    payment_options?: string;
    redirect_url?: string | null;
    customer: { email: string; name?: string; phone_number?: string };
    meta?: Record<string, unknown>;
    customizations?: { title?: string; description?: string; logo?: string };
    [key: string]: unknown;
}

/** What Flutterwave passes to the callback. Always verify it on the server before giving value. */
export interface FlutterwavePayment {
    status: string;
    transaction_id: number;
    tx_ref: string;
    flw_ref: string;
    amount: number;
    currency: string;
    charged_amount?: number;
    customer?: { name?: string; email?: string; phone_number?: string };
    [key: string]: unknown;
}

/** A dynamic account from virtualAccounts()->dynamicFor(). */
export interface FlutterwaveVirtualAccount {
    account_number: string;
    bank_name: string;
    amount?: string;
    currency?: string;
    tx_ref?: string | null;
    note?: string;
    order_ref?: string;
    expiry_date?: string;
    expires_at?: string;
    /** Seconds left when the page was rendered. */
    expires_in?: number;
    [key: string]: unknown;
}

declare global {
    interface Window {
        FlutterwaveCheckout?: (
            config: FlutterwaveCheckoutConfig & {
                callback?: (payment: FlutterwavePayment) => void;
                onclose?: (incomplete: boolean) => void;
            },
        ) => { close: () => void };
    }
}
