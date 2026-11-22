<script setup lang="ts">
import { gsap } from 'gsap';
import { computed, onMounted, ref } from 'vue';
import { prefersReducedMotion } from '@/Composables/useMotion';

/**
 * Lightweight SVG area chart (no chart library: keeps the bundle small and the CSP strict).
 * The line draws itself in with GSAP; hovering shows a tooltip for the nearest day.
 */
const props = defineProps<{ points: { date: string; label: string; revenue: number; orders: number }[]; currency?: string }>();

const W = 720;
const H = 240;
const PAD = 8;
const path = ref<SVGPathElement | null>(null);
const hover = ref<number | null>(null);

const max = computed(() => Math.max(1, ...props.points.map((p) => p.revenue)));
const coords = computed(() =>
    props.points.map((p, i) => ({
        x: PAD + (i / Math.max(1, props.points.length - 1)) * (W - PAD * 2),
        y: H - PAD - (p.revenue / max.value) * (H - PAD * 4),
    })),
);
const line = computed(() =>
    coords.value.reduce((d, p, i, a) => {
        if (i === 0) return `M${p.x},${p.y}`;
        const prev = a[i - 1];
        const cx = (prev.x + p.x) / 2;
        return `${d} C${cx},${prev.y} ${cx},${p.y} ${p.x},${p.y}`;
    }, ''),
);
const area = computed(() => (coords.value.length ? `${line.value} L${coords.value.at(-1)!.x},${H} L${coords.value[0].x},${H} Z` : ''));
const total = computed(() => props.points.reduce((s, p) => s + p.revenue, 0));
const orders = computed(() => props.points.reduce((s, p) => s + p.orders, 0));
const fmt = (n: number) => new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(n);

function onMove(event: PointerEvent): void {
    const svg = event.currentTarget as SVGSVGElement;
    const ratio = (event.clientX - svg.getBoundingClientRect().left) / svg.getBoundingClientRect().width;
    hover.value = Math.round(ratio * (props.points.length - 1));
}

onMounted(() => {
    if (!path.value || prefersReducedMotion()) return;
    const length = path.value.getTotalLength();
    gsap.fromTo(path.value, { strokeDasharray: length, strokeDashoffset: length }, { strokeDashoffset: 0, duration: 1.4, ease: 'power2.out' });
});
</script>

<template>
    <div>
        <div class="mb-4 flex flex-wrap items-baseline gap-x-6 gap-y-1">
            <p class="text-2xl font-semibold text-mono tabular-nums" dir="ltr">{{ fmt(total) }} <span class="text-sm font-normal text-muted-foreground">{{ currency }}</span></p>
            <p class="text-sm text-muted-foreground tabular-nums">{{ orders }} ✦</p>
        </div>
        <div class="relative" dir="ltr">
            <svg :viewBox="`0 0 ${W} ${H}`" class="h-60 w-full overflow-visible" preserveAspectRatio="none" role="img" @pointermove="onMove" @pointerleave="hover = null">
                <defs>
                    <linearGradient id="rev-fill" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.28" />
                        <stop offset="100%" stop-color="var(--primary)" stop-opacity="0" />
                    </linearGradient>
                </defs>
                <g class="text-border" stroke="currentColor" stroke-dasharray="4 6">
                    <line v-for="i in 4" :key="i" :x1="0" :x2="W" :y1="(H / 4) * i - PAD" :y2="(H / 4) * i - PAD" />
                </g>
                <path :d="area" fill="url(#rev-fill)" />
                <path ref="path" :d="line" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                <template v-if="hover !== null && coords[hover]">
                    <line :x1="coords[hover].x" :x2="coords[hover].x" y1="0" :y2="H" stroke="var(--primary)" stroke-opacity="0.3" vector-effect="non-scaling-stroke" />
                    <circle :cx="coords[hover].x" :cy="coords[hover].y" r="5" fill="var(--background)" stroke="var(--primary)" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                </template>
            </svg>
            <div
                v-if="hover !== null && points[hover]"
                class="pointer-events-none absolute -top-2 rounded-lg border border-border bg-popover px-3 py-2 text-xs shadow-md"
                :style="{ left: `${(coords[hover].x / W) * 100}%`, transform: 'translateX(-50%)' }"
            >
                <p class="font-medium text-mono">{{ points[hover].label }}</p>
                <p class="tabular-nums text-muted-foreground">{{ fmt(points[hover].revenue) }} {{ currency }} · {{ points[hover].orders }}</p>
            </div>
            <div class="mt-2 flex justify-between text-xs text-muted-foreground">
                <span>{{ points[0]?.label }}</span>
                <span>{{ points[Math.floor(points.length / 2)]?.label }}</span>
                <span>{{ points.at(-1)?.label }}</span>
            </div>
        </div>
    </div>
</template>
