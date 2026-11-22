<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PaginationLink } from '@/types';

defineProps<{ links: PaginationLink[] }>();

/** Laravel labels prev/next with HTML entities ("&laquo; Previous"); strip them to plain arrows. */
function label(raw: string): string {
    return raw.replace(/&laquo;|&raquo;/g, '').trim();
}
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center justify-center gap-1.5" aria-label="Pagination">
        <template v-for="(link, i) in links" :key="i">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                :class="[
                    'inline-flex min-h-10 min-w-10 items-center justify-center rounded-full px-3 text-sm font-medium transition',
                    link.active ? 'bg-cocoa text-white' : 'bg-white text-stone-700 ring-1 ring-stone-200 hover:ring-stone-300',
                ]"
                :aria-current="link.active ? 'page' : undefined"
            >
                {{ label(link.label) }}
            </Link>
            <span v-else class="inline-flex min-h-10 min-w-10 items-center justify-center px-2 text-sm text-stone-400">{{ label(link.label) }}</span>
        </template>
    </nav>
</template>
