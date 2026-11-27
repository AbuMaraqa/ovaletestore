<script setup lang="ts">
import { computed } from 'vue';
import { useTrans } from '@/Composables/useTrans';

/**
 * How Google would show the page: the same title rule as the server (laravel-seo transformer:
 * "Title | Site", ~60 visible characters) and a 160-character description, with length hints.
 */
const props = defineProps<{ title: string; description: string; url: string; siteName: string; dir: 'rtl' | 'ltr' }>();
const { t } = useTrans();

const strip = (v: string) => v.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
const title = computed(() => {
    const base = strip(props.title).slice(0, 70);
    if (!base) return props.siteName;
    return base.includes(props.siteName) ? base : `${base} | ${props.siteName}`;
});
const description = computed(() => strip(props.description));
const crumbs = computed(() => {
    try {
        const u = new URL(props.url);
        // Google shows Arabic/Hebrew slugs readable, not percent-encoded.
        return [u.host, ...u.pathname.split('/').filter(Boolean).map((s) => decodeURIComponent(s))].join(' › ');
    } catch {
        return props.url;
    }
});
const cut = (v: string, n: number) => (v.length > n ? `${v.slice(0, n - 1)}…` : v);
</script>

<template>
    <div class="rounded-lg border border-border bg-background p-4" :dir="dir">
        <p class="mb-1 flex items-center gap-2 text-xs text-muted-foreground">
            <span class="grid size-6 place-items-center rounded-full bg-muted text-[10px] font-semibold text-mono">{{ siteName.slice(0, 1) }}</span>
            <span class="min-w-0">
                <span class="block text-foreground">{{ siteName }}</span>
                <span class="block truncate" dir="ltr">{{ crumbs }}</span>
            </span>
        </p>
        <p class="text-lg leading-snug text-[#1a0dab] dark:text-[#8ab4f8]">{{ cut(title, 60) }}</p>
        <p v-if="description" class="mt-1 text-sm leading-relaxed text-muted-foreground">{{ cut(description, 160) }}</p>
        <p v-else class="mt-1 text-sm italic text-muted-foreground">{{ t('seo.no_description') }}</p>

        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs tabular-nums">
            <span :class="title.length > 60 ? 'text-warning' : 'text-muted-foreground'">
                {{ t('seo.title_length', { count: title.length }) }}<template v-if="title.length > 60"> — {{ t('seo.too_long') }}</template>
            </span>
            <span :class="description.length > 160 ? 'text-warning' : 'text-muted-foreground'">
                {{ t('seo.description_length', { count: description.length }) }}<template v-if="description.length > 160"> — {{ t('seo.too_long') }}</template>
            </span>
        </div>
    </div>
</template>
