import { router } from '@inertiajs/react';
import { urlWith } from './admin';
import type { FlutterwavePage } from './types';

export function Pager({ page, reloadOnly }: { page: FlutterwavePage | null; reloadOnly?: string[] }) {
    if (!page || page.total_pages <= 1) {
        return null;
    }

    const go = (to: number) => router.visit(urlWith({ page: to }), { preserveScroll: true, preserveState: true, ...(reloadOnly ? { only: reloadOnly } : {}) });

    return (
        <nav aria-label="Pages" className="flw-pager">
            {page.current_page > 1 && <button type="button" onClick={() => go(page.current_page - 1)} className="flw-btn flw-btn--outline flw-btn--sm">Previous</button>}
            <span className="flw-muted">Page {page.current_page} of {page.total_pages}</span>
            {page.current_page < page.total_pages && <button type="button" onClick={() => go(page.current_page + 1)} className="flw-btn flw-btn--outline flw-btn--sm">Next</button>}
        </nav>
    );
}
