<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { useTrans } from '@/Composables/useTrans';
import type { PaginationLink } from '@/types';

/**
 * Quiet pagination: "previous" / "next" with arrows and a short window of pages (1 … 4 5 6 … 22) instead of
 * Laravel's ten-plus circles. Phones show "Page 4 of 22" between the arrows. Built from the paginator's `links`
 * (works with both plain and API-resource paginators); page URLs keep every other query parameter (filters, sort).
 */
const props = defineProps<{ links: PaginationLink[] }>();
const { t } = useTrans();

/** Laravel labels prev/next with HTML entities ("&laquo; Previous"); strip them. */
const clean = (raw: string) => raw.replace(/&laquo;|&raquo;/g, '').trim();

const prev = computed(() => props.links[0]);
const next = computed(() => props.links[props.links.length - 1]);
const numbered = computed(() => props.links.slice(1, -1).filter((l) => /^\d+$/.test(l.label)));
const current = computed(() => Number(numbered.value.find((l) => l.active)?.label ?? 1));
const last = computed(() => Math.max(1, ...numbered.value.map((l) => Number(l.label))));

/** Any page's URL with `page` swapped (the paginator only lists some pages). */
const sample = computed(() => props.links.find((l) => l.url)?.url ?? null);
function urlFor(page: number): string | null {
    if (!sample.value) return null;
    const url = new URL(sample.value, window.location.origin);
    url.searchParams.set('page', String(page));
    return url.pathname + url.search;
}

/** First, last, and the current page with its neighbours (near an edge: the first/last four); gaps become "…". */
const pages = computed<(number | null)[]>(() => {
    const total = last.value;
    const c = current.value;
    const keep = new Set([1, total, c - 1, c, c + 1]);
    if (c <= 3) [2, 3, 4].forEach((p) => keep.add(p));
    if (c >= total - 2) [total - 3, total - 2, total - 1].forEach((p) => keep.add(p));
    const sorted = [...keep].filter((p) => p >= 1 && p <= total).sort((a, b) => a - b);

    const out: (number | null)[] = [];
    sorted.forEach((p, i) => {
        const gap = i ? p - sorted[i - 1] : 1;
        if (gap === 2) out.push(p - 1); // a single hidden page: show it rather than "…"
        else if (gap > 2) out.push(null);
        out.push(p);
    });
    return out;
});

const edge = 'inline-flex h-11 items-center gap-2 rounded-full px-4 text-sm font-semibold transition';
</script>

<template>
    <nav v-if="last > 1" class="flex items-center justify-between gap-3 sm:justify-center sm:gap-6" :aria-label="t('storefront.page_of', { current, last })">
        <Link v-if="prev?.url" :href="prev.url" preserve-scroll rel="prev" :class="[edge, 'text-cocoa hover:bg-sand']">
            <Icon name="arrow" class="size-4 rotate-180 rtl:rotate-0" />
            {{ clean(prev.label) }}
        </Link>
        <span v-else :class="[edge, 'text-stone-300']" aria-hidden="true">
            <Icon name="arrow" class="size-4 rotate-180 rtl:rotate-0" />
            {{ clean(prev?.label ?? '') }}
        </span>

        <ol class="hidden items-center gap-1 sm:flex">
            <li v-for="(page, i) in pages" :key="i">
                <span v-if="page === null" class="inline-flex size-10 items-end justify-center pb-2 text-stone-400" aria-hidden="true">…</span>
                <span
                    v-else-if="page === current"
                    class="inline-flex size-10 items-center justify-center rounded-full bg-cocoa text-sm font-bold text-white tabular-nums"
                    aria-current="page"
                >
                    {{ page }}
                </span>
                <Link
                    v-else
                    :href="urlFor(page) ?? '#'"
                    preserve-scroll
                    class="inline-flex size-10 items-center justify-center rounded-full text-sm font-medium text-stone-600 tabular-nums transition hover:bg-sand hover:text-cocoa"
                >
                    {{ page }}
                </Link>
            </li>
        </ol>
        <p class="text-sm text-stone-500 tabular-nums sm:hidden">{{ t('storefront.page_of', { current, last }) }}</p>

        <Link v-if="next?.url" :href="next.url" preserve-scroll rel="next" :class="[edge, 'bg-cocoa text-white hover:bg-brand-800']">
            {{ clean(next.label) }}
            <Icon name="arrow" class="size-4 rtl:-scale-x-100" />
        </Link>
        <span v-else :class="[edge, 'text-stone-300']" aria-hidden="true">
            {{ clean(next?.label ?? '') }}
            <Icon name="arrow" class="size-4 rtl:-scale-x-100" />
        </span>
    </nav>
</template>
