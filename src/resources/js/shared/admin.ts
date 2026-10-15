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

/** Renders as a status pill: <span class="flw-pill" data-tone={tone}>. */
export type ToneAttribute = Tone;

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

/** Colour for a Flutterwave status: successful, completed and settled are good; failed and flagged are bad. */
export function statusTone(status: string | null | undefined): Tone {
    const value = (status ?? '').toLowerCase();

    if (['successful', 'completed', 'settled', 'success'].includes(value) || value.startsWith('completed')) {
        return 'good';
    }

    if (['failed', 'flagged', 'error', 'cancelled'].includes(value)) {
        return 'bad';
    }

    return ['pending', 'new', 'processing'].includes(value) ? 'attention' : 'neutral';
}

/** "Completed" for "completed-mpgs", "Successful" for "successful". */
export function statusLabel(status: string | null | undefined): string {
    const value = (status ?? '').split('-')[0];

    return value ? value[0].toUpperCase() + value.slice(1) : '';
}

export function formatDate(value: string | null | undefined, withTime = true): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString(undefined, withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' });
}

/** The current page's URL with some query parameters changed; null or '' removes one. Changing filters resets the page. */
export function urlWith(params: Record<string, string | number | null | undefined>, href: string = window.location.href): string {
    const url = new URL(href);

    if (!('page' in params)) {
        url.searchParams.delete('page');
    }

    for (const [key, value] of Object.entries(params)) {
        if (value === null || value === undefined || value === '') {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, String(value));
        }
    }

    return url.pathname + url.search + url.hash;
}

/** Banks whose name or code matches what the user typed. */
export function filterBanks(banks: { code: string; name: string }[], term: string, keep?: string): { code: string; name: string }[] {
    const needle = term.trim().toLowerCase();

    return needle === '' ? banks : banks.filter((bank) => bank.name.toLowerCase().includes(needle) || bank.code.startsWith(needle) || bank.code === keep);
}
