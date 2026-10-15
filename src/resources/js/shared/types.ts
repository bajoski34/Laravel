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
    /** Present when FLW_CHECKSUM is on: Flutterwave rejects the checkout if amount, currency, email or tx_ref changed. */
    payload_hash?: string;
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

/** One chargeback from chargebacks()->panel(). */
export interface FlutterwaveChargeback {
    id: number;
    amount: number | null;
    status: 'initiated' | 'pending' | 'accepted' | 'declined' | 'won' | 'lost' | 'reversed' | string;
    stage: string | null;
    comment: string | null;
    flw_ref: string | null;
    tx_ref: string | null;
    transaction_id: number | null;
    due_at: string | null;
    /** Seconds left to respond when the page was rendered; null once it's no longer open. */
    respond_within: number | null;
    proof: string | null;
    history: { action: string | null; date: string | null; description: string | null; comment: string | null }[];
    /** Present when the item is open and Route::flutterwaveAdmin() is registered. */
    urls: { accept: string; decline: string } | null;
}

/** From chargebacks()->panel(). */
export interface FlutterwaveChargebackPanel {
    items: FlutterwaveChargeback[];
    page: { total: number; current_page: number; total_pages: number; page_size?: number } | null;
    error: string | null;
    proof: { types: string[]; max_bytes: number };
}

export interface FlutterwaveFxQuote {
    id: string;
    reference: string | null;
    status: 'NEW' | 'PROCESSING' | 'READY' | 'EXPIRED' | 'FAILED' | string;
    base: string | null;
    target: string | null;
    quantity: number | null;
    approved_quantity: number | null;
    rate: string | null;
    total_value: number | null;
    expires_at: string | null;
    /** Seconds the rate is held for, counted when the page was rendered. */
    expires_in: number | null;
    message: string | null;
    pending: boolean;
    tradable: boolean;
}

export interface FlutterwaveFxTrade {
    id: string;
    status: 'NEW' | 'PENDING' | 'SETTLED' | 'FAILED' | string;
    base: string | null;
    target: string | null;
    sold: number | null;
    bought: number | null;
    recipient: string | null;
    narration: string | null;
    reference: string | null;
    message: string | null;
    created_at: string | null;
    pending: boolean;
}

/** From fx()->panel($quoteId, $tradeId). */
export interface FlutterwaveFxPanel {
    quote: FlutterwaveFxQuote | null;
    trade: FlutterwaveFxTrade | null;
    error: string | null;
    pairs: [string, string][];
    /** Present when Route::flutterwaveAdmin() is registered. */
    urls: { quote: string; trade: string } | null;
}

/** Validation errors from the package's routes, as Inertia passes them to onError. */
export type FlutterwaveErrors = Record<string, string>;
