<script setup lang="ts">
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { prefersReducedMotion } from '@/Composables/useMotion';

/**
 * Scroll Velocity (after React Bits' text animation, rebuilt for Vue + GSAP; no React/motion dependency): rows of
 * words drifting sideways in alternating directions. Scrolling speeds them up in proportion to the scroll speed
 * (up to ×6) and scrolling back up reverses them; they ease back to the base speed when the page rests.
 *
 * - Each track holds 3 copies of its row and wraps by exactly one copy's width, so the loop is seamless.
 * - Rows and tracks are laid out LTR so a track starts at the row's left edge (in an RTL row it would hang off the
 *   right edge and a short row would wrap out of view); each word keeps its own direction (dir="auto").
 * - Runs only while on screen (ScrollTrigger isActive); static with prefers-reduced-motion.
 * - Decorative: hidden from assistive tech (the same words are real links elsewhere on the page).
 * - Look: a full-width sand band between hairlines; the first row in large type, the next as small framed words.
 */
const props = withDefaults(defineProps<{ items: string[]; velocity?: number; rows?: number }>(), { velocity: 55, rows: 2 });

const COPIES = 3;
const root = ref<HTMLElement | null>(null);
const lines = computed(() => Array.from({ length: props.rows }, (_, r) => (r % 2 ? [...props.items].reverse() : props.items)));

let tick: (() => void) | undefined;
let trigger: ScrollTrigger | undefined;
let resize: ResizeObserver | undefined;

onMounted(() => {
    if (!root.value || prefersReducedMotion() || !props.items.length) return;

    const tracks = [...root.value.querySelectorAll<HTMLElement>('[data-sv-track]')];
    const setX = tracks.map((track) => gsap.quickSetter(track, 'x', 'px'));
    const offset = tracks.map(() => 0);
    let widths: number[] = [];
    const measure = () => (widths = tracks.map((track) => (track.firstElementChild as HTMLElement).offsetWidth));
    measure();
    resize = new ResizeObserver(measure);
    resize.observe(root.value);
    tracks.forEach((track) => resize?.observe(track.firstElementChild as HTMLElement));
    void document.fonts?.ready.then(measure); // widths change once the brand font has loaded

    trigger = ScrollTrigger.create({ trigger: root.value, start: 'top bottom', end: 'bottom top' });

    let lastY = window.scrollY;
    let speed = 0; // smoothed scroll speed, px/s (signed)
    let direction = 1;

    tick = () => {
        const dt = gsap.ticker.deltaRatio(60) / 60;
        const y = window.scrollY;
        speed += ((y - lastY) / Math.max(dt, 1 / 240) - speed) * 0.12;
        lastY = y;
        if (!trigger?.isActive) return;

        if (speed < -5) direction = -1;
        else if (speed > 5) direction = 1;
        const boost = Math.min(5, (Math.abs(speed) / 1000) * 5);

        tracks.forEach((_, i) => {
            const sign = i % 2 ? -1 : 1;
            const move = sign * direction * props.velocity * dt * (1 + boost);
            offset[i] = gsap.utils.wrap(-(widths[i] || 1), 0, offset[i] - move);
            setX[i](offset[i]);
        });
    };
    gsap.ticker.add(tick);
});

onBeforeUnmount(() => {
    if (tick) gsap.ticker.remove(tick);
    trigger?.kill();
    resize?.disconnect();
});
</script>

<template>
    <!-- Full-width sand band framed by hairlines (top / between rows / bottom), faded at both edges. -->
    <section aria-hidden="true">
        <div ref="root" class="overflow-hidden border-y border-hairline bg-sand">
            <div
                v-for="(line, r) in lines"
                :key="r"
                dir="ltr"
                :class="[
                    'overflow-hidden mask-[linear-gradient(90deg,transparent,#000_6%,#000_94%,transparent)]',
                    r ? 'border-t border-hairline py-4 lg:py-5' : 'py-3 lg:py-4',
                ]"
            >
                <div data-sv-track dir="ltr" class="flex w-max will-change-transform">
                    <span v-for="copy in COPIES" :key="copy" class="flex shrink-0 items-center">
                        <template v-for="(word, w) in line" :key="w">
                            <!-- Odd rows: each word in a small white frame. -->
                            <span
                                v-if="r % 2"
                                dir="auto"
                                class="mx-1.5 rounded-full bg-white px-5 py-1.5 text-base leading-relaxed font-bold whitespace-nowrap text-brand-700 ring-1 ring-hairline sm:mx-2 sm:px-6 sm:text-xl"
                            >
                                {{ word }}
                            </span>
                            <template v-else>
                                <span dir="auto" class="px-5 text-4xl leading-[1.6] font-extrabold whitespace-nowrap text-cocoa sm:px-7 sm:text-5xl lg:text-6xl">
                                    {{ word }}
                                </span>
                                <span class="size-2.5 shrink-0 rounded-full bg-gold" />
                            </template>
                        </template>
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
