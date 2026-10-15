/** The chevron and check from Flutterwave's WooCommerce admin. */
export function Chevron() {
    return (
        <span className="flw-select__chevron" aria-hidden="true">
            <svg viewBox="0 0 16 16" width="16" height="16"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" /></svg>
        </span>
    );
}

export function CheckBox() {
    return (
        <span className="flw-checkbox__box" aria-hidden="true">
            <svg viewBox="0 0 16 16" width="12" height="12"><path d="m3.5 8.4 3 3 6-6.8" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" /></svg>
        </span>
    );
}

export function Notice({ text, ok }: { text: string; ok: boolean }) {
    return (
        <p role={ok ? 'status' : 'alert'} className={`flw-notice ${ok ? 'flw-notice--success' : 'flw-notice--error'}`}>
            {text}
        </p>
    );
}
