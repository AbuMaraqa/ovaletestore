<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { usePageTransition } from '@/Composables/usePageTransition';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

/**
 * Page-transition loader (GSAP, see usePageTransition): a slim top progress bar on every navigation and,
 * for slow page loads, a small floating "loading" badge with the store mark. Purely visual: the content
 * region gets aria-busy while it loads.
 */
const props = withDefaults(defineProps<{ content?: string; tone?: 'store' | 'admin' }>(), { content: 'main', tone: 'store' });

const page = usePage<SharedProps>();
const { t } = useTrans();
const bar = ref<HTMLElement | null>(null);
const badge = ref<HTMLElement | null>(null);
const { badgeVisible } = usePageTransition(bar, badge, props.content, props.tone === 'admin' ? { dim: 0.7, rise: 6 } : { dim: 0.6, rise: 10 });

const logo = computed(() => page.props.site?.logo ?? null);
const initial = computed(() => (page.props.site?.name ?? '').trim().charAt(0));
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-0 z-[120]" aria-hidden="true">
        <div
            ref="bar"
            :class="[
                'page-loader-bar invisible w-full scale-x-0 opacity-0 ltr:origin-left rtl:origin-right',
                tone === 'admin' ? 'h-[2px]' : 'h-[3px]',
                tone === 'admin' ? 'page-loader-bar--admin' : 'page-loader-bar--store',
            ]"
        />
    </div>

    <div v-show="badgeVisible" class="pointer-events-none fixed inset-x-0 top-5 z-[120] flex justify-center px-4">
        <div
            ref="badge"
            role="status"
            :class="[
                'flex items-center gap-2.5 rounded-full py-1.5 pe-4 text-sm font-semibold shadow-lg ring-1 backdrop-blur-md',
                tone === 'admin' ? 'ps-4' : 'ps-1.5',
                tone === 'admin' ? 'bg-background/90 text-foreground ring-border' : 'bg-white/90 text-cocoa ring-stone-200',
            ]"
        >
        <span v-if="tone !== 'admin'" class="page-loader-mark relative flex size-8 items-center justify-center overflow-hidden rounded-full">
            <img v-if="logo" :src="logo" alt="" class="size-6 object-contain" width="24" height="24" />
            <span v-else class="text-sm font-bold">{{ initial }}</span>
        </span>
        <span>{{ t('common.loading') }}</span>
        <span class="page-loader-dots flex gap-1" aria-hidden="true"><i /><i /><i /></span>
        </div>
    </div>
</template>

<style>
.page-loader-bar {
    border-radius: 0 999px 999px 0;
    box-shadow: 0 0 10px var(--loader-glow);
}
.page-loader-bar--store {
    --loader-glow: color-mix(in oklab, var(--color-brand-500) 60%, transparent);
    background: linear-gradient(90deg, var(--color-brand-700), var(--color-brand-500) 55%, #c9a24d);
}
.page-loader-bar--admin {
    --loader-glow: color-mix(in oklab, var(--primary) 55%, transparent);
    background: linear-gradient(90deg, var(--primary), color-mix(in oklab, var(--primary) 55%, #c9a24d));
}
[dir='rtl'] .page-loader-bar {
    border-radius: 999px 0 0 999px;
    background-image: linear-gradient(270deg, var(--color-brand-700), var(--color-brand-500) 55%, #c9a24d);
}
[dir='rtl'] .page-loader-bar--admin {
    background-image: linear-gradient(270deg, var(--primary), color-mix(in oklab, var(--primary) 55%, #c9a24d));
}
.page-loader-mark {
    background: color-mix(in oklab, var(--color-brand-500) 12%, white);
}
.page-loader-mark::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    border: 2px solid transparent;
    border-top-color: var(--color-brand-600);
    animation: page-loader-spin 0.9s linear infinite;
}
.page-loader-dots i {
    width: 5px;
    height: 5px;
    border-radius: 999px;
    background: currentColor;
    opacity: 0.25;
    animation: page-loader-dot 1s ease-in-out infinite;
}
.page-loader-dots i:nth-child(2) { animation-delay: 0.15s; }
.page-loader-dots i:nth-child(3) { animation-delay: 0.3s; }
@keyframes page-loader-spin { to { transform: rotate(360deg); } }
@keyframes page-loader-dot { 50% { opacity: 1; transform: translateY(-2px); } }
@media (prefers-reduced-motion: reduce) {
    .page-loader-mark::after, .page-loader-dots i { animation: none; }
    .page-loader-dots i { opacity: 0.6; }
}
</style>
