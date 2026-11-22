<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PaginationLink } from '@/types';

defineProps<{ links: PaginationLink[]; total?: number; from?: number | null; to?: number | null }>();
const clean = (label: string) => label.replace(/&laquo;|&raquo;/g, '').trim();
</script>

<template>
    <div v-if="links.length > 3" class="flex flex-col items-center justify-between gap-3 text-sm text-muted-foreground sm:flex-row">
        <span v-if="total !== undefined" class="tabular-nums">{{ from ?? 0 }}–{{ to ?? 0 }} / {{ total }}</span>
        <nav class="flex flex-wrap gap-1">
            <template v-for="(link, i) in links" :key="i">
                <Link v-if="link.url" :href="link.url" preserve-scroll preserve-state :class="['kt-btn kt-btn-sm', link.active ? '' : 'kt-btn-ghost']">{{ clean(link.label) }}</Link>
                <span v-else class="kt-btn kt-btn-sm kt-btn-ghost pointer-events-none opacity-50">{{ clean(link.label) }}</span>
            </template>
        </nav>
    </div>
</template>
