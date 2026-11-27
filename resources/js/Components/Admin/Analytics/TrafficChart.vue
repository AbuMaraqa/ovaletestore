<script setup lang="ts">
import { gsap } from 'gsap';
import { computed, onMounted, ref } from 'vue';
import { prefersReducedMotion } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';

export interface TrafficPoint {
    date: string;
    label: string;
    visitors: number;
    views: number;
}

/** Two-series SVG chart (visitors = filled line, page views = dashed line); same no-library approach as RevenueChart. */
const props = withDefaults(defineProps<{ points: TrafficPoint[]; compact?: boolean }>(), { compact: false });
const { t } = useTrans();

const W = 720;
const H = computed(() => (props.compact ? 140 : 240));
const PAD = 8;
const line = ref<SVGPathElement | null>(null);
const hover = ref<number | null>(null);

const max = computed(() => Math.max(1, ...props.points.map((p) => Math.max(p.visitors, p.views))));
const coords = (key: 'visitors' | 'views') =>
    props.points.map((p, i) => ({
        x: PAD + (i / Math.max(1, props.points.length - 1)) * (W - PAD * 2),
        y: H.value - PAD - (p[key] / max.value) * (H.value - PAD * 4),
    }));
const smooth = (pts: { x: number; y: number }[]) =>
    pts.reduce((d, p, i, a) => {
        if (i === 0) return `M${p.x},${p.y}`;
        const cx = (a[i - 1].x + p.x) / 2;
        return `${d} C${cx},${a[i - 1].y} ${cx},${p.y} ${p.x},${p.y}`;
    }, '');
const visitors = computed(() => coords('visitors'));
const visitorsPath = computed(() => smooth(visitors.value));
const viewsPath = computed(() => smooth(coords('views')));
const area = computed(() => (visitors.value.length ? `${visitorsPath.value} L${visitors.value.at(-1)!.x},${H.value} L${visitors.value[0].x},${H.value} Z` : ''));
const fmt = (n: number) => new Intl.NumberFormat(undefined).format(n);

function onMove(event: PointerEvent): void {
    const box = (event.currentTarget as SVGSVGElement).getBoundingClientRect();
    hover.value = Math.round(((event.clientX - box.left) / box.width) * (props.points.length - 1));
}

onMounted(() => {
    if (!line.value || prefersReducedMotion()) return;
    const length = line.value.getTotalLength();
    gsap.fromTo(line.value, { strokeDasharray: length, strokeDashoffset: length }, { strokeDashoffset: 0, duration: 1.2, ease: 'power2.out' });
});
</script>

<template>
    <div>
        <div v-if="!compact" class="mb-3 flex gap-4 text-xs text-muted-foreground">
            <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 rounded bg-primary" /> {{ t('analytics.visitors') }}</span>
            <span class="flex items-center gap-1.5"><span class="h-0 w-4 border-t-2 border-dashed border-info" /> {{ t('analytics.views') }}</span>
        </div>
        <div class="relative" dir="ltr">
            <svg :viewBox="`0 0 ${W} ${H}`" :class="['w-full overflow-visible', compact ? 'h-32' : 'h-60']" preserveAspectRatio="none" role="img" @pointermove="onMove" @pointerleave="hover = null">
                <defs>
                    <linearGradient id="traffic-fill" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.25" />
                        <stop offset="100%" stop-color="var(--primary)" stop-opacity="0" />
                    </linearGradient>
                </defs>
                <g v-if="!compact" class="text-border" stroke="currentColor" stroke-dasharray="4 6">
                    <line v-for="i in 4" :key="i" :x1="0" :x2="W" :y1="(H / 4) * i - PAD" :y2="(H / 4) * i - PAD" />
                </g>
                <path :d="area" fill="url(#traffic-fill)" />
                <path :d="viewsPath" fill="none" stroke="var(--color-info, #3b82f6)" stroke-width="1.75" stroke-dasharray="5 5" vector-effect="non-scaling-stroke" />
                <path ref="line" :d="visitorsPath" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                <template v-if="hover !== null && visitors[hover]">
                    <line :x1="visitors[hover].x" :x2="visitors[hover].x" y1="0" :y2="H" stroke="var(--primary)" stroke-opacity="0.3" vector-effect="non-scaling-stroke" />
                    <circle :cx="visitors[hover].x" :cy="visitors[hover].y" r="5" fill="var(--background)" stroke="var(--primary)" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                </template>
            </svg>
            <div
                v-if="hover !== null && points[hover]"
                class="pointer-events-none absolute -top-2 z-10 rounded-lg border border-border bg-popover px-3 py-2 text-xs shadow-md"
                :style="{ left: `${(visitors[hover].x / W) * 100}%`, transform: 'translateX(-50%)' }"
            >
                <p class="font-medium text-mono">{{ points[hover].label }}</p>
                <p class="tabular-nums text-muted-foreground">{{ t('analytics.visitors') }}: {{ fmt(points[hover].visitors) }}</p>
                <p class="tabular-nums text-muted-foreground">{{ t('analytics.views') }}: {{ fmt(points[hover].views) }}</p>
            </div>
            <div v-if="!compact" class="mt-2 flex justify-between text-xs text-muted-foreground">
                <span>{{ points[0]?.label }}</span>
                <span>{{ points[Math.floor(points.length / 2)]?.label }}</span>
                <span>{{ points.at(-1)?.label }}</span>
            </div>
        </div>
    </div>
</template>
