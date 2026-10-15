import type { FlutterwaveChargeback, FlutterwaveErrors } from './types';

export type Tone = 'attention' | 'neutral' | 'info' | 'good' | 'bad';

/** Plain-language label and colour for each chargeback status. */
export function chargebackStatus(chargeback: FlutterwaveChargeback): { label: string; tone: Tone } {
    switch (chargeback.status) {
        case 'initiated':
        case 'pending':
            return { label: 'Needs a response', tone: 'attention' };
        case 'accepted':
            return { label: 'Accepted, customer refunded', tone: 'neutral' };
        case 'declined':
            return { label: 'Disputed, awaiting the bank', tone: 'info' };
        case 'won':
            return { label: 'Won', tone: 'good' };
        case 'lost':
            return { label: 'Lost, customer refunded', tone: 'bad' };
        case 'reversed':
            return { label: 'Reversed, funds restored', tone: 'good' };
        default:
            return { label: chargeback.status, tone: 'neutral' };
    }
}

export const toneClasses: Record<Tone, string> = {
    attention: 'bg-amber-100 text-amber-900 dark:bg-amber-400/15 dark:text-amber-300',
    neutral: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
    info: 'bg-sky-100 text-sky-900 dark:bg-sky-400/15 dark:text-sky-300',
    good: 'bg-green-100 text-green-900 dark:bg-green-400/15 dark:text-green-300',
    bad: 'bg-red-100 text-red-900 dark:bg-red-400/15 dark:text-red-300',
};

/** "2 days 4 h", "6 h 12 min", "45 min". For deadlines measured in hours, not seconds. */
export function formatDeadline(seconds: number): string {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (days > 0) {
        return `${days} ${days === 1 ? 'day' : 'days'}${hours ? ` ${hours} h` : ''}`;
    }

    if (hours > 0) {
        return `${hours} h${minutes ? ` ${minutes} min` : ''}`;
    }

    return `${Math.max(1, minutes)} min`;
}

/** Check a proof file in the browser, with the same rules the server enforces. */
export function checkProof(file: File | null, rules: { types: string[]; max_bytes: number }): string | null {
    if (!file) {
        return 'Choose the file with your evidence.';
    }

    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

    if (!rules.types.includes(extension)) {
        return 'The proof must be a JPEG, PNG or PDF file.';
    }

    if (file.size >= rules.max_bytes) {
        return 'The proof must be smaller than 8MB. Merge fewer pages, or compress the file.';
    }

    return null;
}

/** The first error to show from an Inertia onError payload. */
export function firstError(errors: FlutterwaveErrors): string {
    return errors.flutterwave ?? Object.values(errors)[0] ?? 'Something went wrong. Try again.';
}

export function formatMoney(currency: string | null, amount: number | string | null): string {
    if (amount === null || amount === '' || Number.isNaN(Number(amount))) {
        return '';
    }

    return `${currency ?? ''} ${Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`.trim();
}

/** The current page's URL without the fx_quote and fx_trade parameters, to start a new conversion. */
export function fxStartOverUrl(href: string = window.location.href): string {
    const url = new URL(href);
    url.searchParams.delete('fx_quote');
    url.searchParams.delete('fx_trade');

    return url.pathname + url.search + url.hash;
}
