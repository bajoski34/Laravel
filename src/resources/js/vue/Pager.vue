<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { urlWith } from './admin';
import type { FlutterwavePage } from './types';

const props = defineProps<{ page: FlutterwavePage | null; reloadOnly?: string[] }>();

function go(to: number) {
    router.visit(urlWith({ page: to }), { preserveScroll: true, preserveState: true, ...(props.reloadOnly ? { only: props.reloadOnly } : {}) });
}
</script>

<template>
    <nav v-if="page && page.total_pages > 1" aria-label="Pages" class="flw-pager">
        <button v-if="page.current_page > 1" type="button" class="flw-btn flw-btn--outline flw-btn--sm" @click="go(page.current_page - 1)">Previous</button>
        <span class="flw-muted">Page {{ page.current_page }} of {{ page.total_pages }}</span>
        <button v-if="page.current_page < page.total_pages" type="button" class="flw-btn flw-btn--outline flw-btn--sm" @click="go(page.current_page + 1)">Next</button>
    </nav>
</template>
