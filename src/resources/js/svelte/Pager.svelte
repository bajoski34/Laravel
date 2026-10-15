<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { urlWith } from './admin';
    import type { FlutterwavePage } from './types';

    let { page, reloadOnly }: { page: FlutterwavePage | null; reloadOnly?: string[] } = $props();

    function go(to: number) {
        router.visit(urlWith({ page: to }), { preserveScroll: true, preserveState: true, ...(reloadOnly ? { only: reloadOnly } : {}) });
    }
</script>

{#if page && page.total_pages > 1}
    <nav aria-label="Pages" class="flw-pager">
        {#if page.current_page > 1}<button type="button" class="flw-btn flw-btn--outline flw-btn--sm" onclick={() => go(page.current_page - 1)}>Previous</button>{/if}
        <span class="flw-muted">Page {page.current_page} of {page.total_pages}</span>
        {#if page.current_page < page.total_pages}<button type="button" class="flw-btn flw-btn--outline flw-btn--sm" onclick={() => go(page.current_page + 1)}>Next</button>{/if}
    </nav>
{/if}
