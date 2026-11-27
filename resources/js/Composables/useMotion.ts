import { router } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';
import { onBeforeUnmount, onMounted, type Ref } from 'vue';

gsap.registerPlugin(ScrollTrigger);

/**
 * Motion system for the storefront:
 *  - Lenis smooth scrolling driven by GSAP's ticker, so ScrollTrigger stays in perfect sync.
 *  - Everything is disabled for `prefers-reduced-motion: reduce` (content is simply shown).
 */
let lenis: Lenis | null = null;
const raf = (time: number) => lenis?.raf(time * 1000);

export const prefersReducedMotion = (): boolean =>
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export function startSmoothScroll(): void {
    if (lenis || prefersReducedMotion()) return;

    lenis = new Lenis({ lerp: 0.1, smoothWheel: true, anchors: { offset: -120 } });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add(raf);
    gsap.ticker.lagSmoothing(0);
}

export function stopSmoothScroll(): void {
    gsap.ticker.remove(raf);
    lenis?.destroy();
    lenis = null;
}

/** Pause smooth scrolling while an overlay (drawer/modal) owns the scroll. */
export function lockScroll(locked: boolean): void {
    if (locked) lenis?.stop();
    else lenis?.start();
}

/** Stop any smooth-scroll easing where it is (the next frame must not keep scrolling toward an old target). */
function haltSmoothScroll(): void {
    lenis?.scrollTo(window.scrollY, { immediate: true, force: true });
}

/**
 * Keep Lenis in step with Inertia page swaps. Without this, clicking a link while Lenis is still easing a wheel
 * scroll let it finish that old animation on the NEW page: Inertia had scrolled to the top, Lenis scrolled back to
 * the old offset, which on a shorter page is the footer.
 *  - visit starts → halt any easing;
 *  - page swapped → for a normal visit jump to the top at once; then (next frame, after Inertia restored or kept the
 *    position: back/forward, preserveScroll) sync Lenis to the real scroll position, re-measure, refresh triggers.
 * Returns the unsubscribe function.
 */
export function syncScrollWithNavigation(): () => void {
    let resetToTop = false;
    const offStart = router.on('start', (event) => {
        resetToTop = event.detail.visit.preserveScroll === false;
        haltSmoothScroll();
    });
    const offNavigate = router.on('navigate', () => {
        if (resetToTop) lenis?.scrollTo(0, { immediate: true, force: true });
        resetToTop = false;
        requestAnimationFrame(() => {
            lenis?.resize();
            haltSmoothScroll();
            ScrollTrigger.refresh();
        });
    });
    return () => {
        offStart();
        offNavigate();
    };
}

/**
 * Scoped GSAP animations for a component. Animations are created inside a gsap.context bound
 * to `root` and reverted on unmount (no leaks across Inertia navigations).
 */
export function useGsap(root: Ref<HTMLElement | null>, setup: (ctx: { gsap: typeof gsap; ScrollTrigger: typeof ScrollTrigger; root: HTMLElement }) => void): void {
    let ctx: gsap.Context | undefined;

    onMounted(() => {
        if (!root.value || prefersReducedMotion()) return;
        const el = root.value;
        ctx = gsap.context(() => setup({ gsap, ScrollTrigger, root: el }), el);
    });

    onBeforeUnmount(() => ctx?.revert());
}

/** Fade/slide-up reveal for every `[data-reveal]` child, batched as they enter the viewport. */
export function revealOnScroll(root: HTMLElement): void {
    const items = root.querySelectorAll<HTMLElement>('[data-reveal]');
    if (!items.length) return;

    gsap.set(items, { autoAlpha: 0, y: 36 });
    ScrollTrigger.batch(items, {
        start: 'top 88%',
        once: true,
        onEnter: (batch) => gsap.to(batch, { autoAlpha: 1, y: 0, duration: 0.8, ease: 'power3.out', stagger: 0.08, overwrite: true }),
    });
}
