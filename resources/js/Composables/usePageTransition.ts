import { router } from '@inertiajs/vue3';
import type { gsap as Gsap } from 'gsap';
import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue';

type Visit = { method: string; prefetch?: boolean; async?: boolean; only?: string[]; preserveState?: unknown };

const reducedMotion = (): boolean => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * GSAP page transitions for Inertia visits, shared by every layout:
 *  - a top progress bar that sprints, then crawls, then completes (any visit that takes > 120 ms);
 *  - on real page changes (GET, not preserving state): the content dims while loading, a small loading
 *    badge appears if it takes > 700 ms, and the new page eases in.
 * Polling, prefetching and partial reloads never trigger it; filter/sort visits show only the bar.
 * Reduced motion: the bar and badge still appear, without movement.
 */
export function usePageTransition(bar: Ref<HTMLElement | null>, badge: Ref<HTMLElement | null>, contentSelector: string, options: { dim?: number; rise?: number } = {}) {
    const dim = options.dim ?? 0.6;
    const rise = options.rise ?? 10;
    const badgeVisible = ref(false);
    let gsap: typeof Gsap | null = null;
    let active = false;
    let pageVisit = false;
    let showTimer: number | undefined;
    let badgeTimer: number | undefined;
    let crawl: gsap.core.Timeline | null = null;
    const content = () => document.querySelector<HTMLElement>(contentSelector);

    function begin(visit: Visit): void {
        if (visit.prefetch || visit.async || (visit.only?.length ?? 0) > 0) return;
        finishNow();
        active = true;
        pageVisit = visit.method === 'get' && visit.preserveState !== true;
        showTimer = window.setTimeout(show, 120);
    }

    function show(): void {
        if (!active || !gsap || !bar.value) return;
        const g = gsap;
        const instant = reducedMotion();
        g.killTweensOf(bar.value);
        g.set(bar.value, { autoAlpha: 1, scaleX: instant ? 0.9 : 0 });
        if (!instant) {
            crawl = g.timeline()
                .to(bar.value, { scaleX: 0.4, duration: 0.45, ease: 'power2.out' })
                .to(bar.value, { scaleX: 0.92, duration: 9, ease: 'power1.out' });
        }
        if (!pageVisit) return;
        const main = content();
        if (main) {
            main.setAttribute('aria-busy', 'true');
            g.to(main, { opacity: dim, duration: instant ? 0 : 0.3, ease: 'power1.out' });
        }
        badgeTimer = window.setTimeout(() => {
            if (!active || !badge.value) return;
            badgeVisible.value = true;
            if (!instant) g.fromTo(badge.value, { autoAlpha: 0, y: -8, scale: 0.96 }, { autoAlpha: 1, y: 0, scale: 1, duration: 0.3, ease: 'back.out(2)' });
            else g.set(badge.value, { autoAlpha: 1 });
        }, 580);
    }

    /** The new page is in the DOM: bring it in. */
    function entered(): void {
        if (!active || !pageVisit || !gsap) return;
        const main = content();
        if (!main) return;
        gsap.killTweensOf(main);
        if (reducedMotion()) gsap.set(main, { clearProps: 'opacity' });
        else gsap.fromTo(main, { opacity: 0, y: rise }, { opacity: 1, y: 0, duration: 0.45, ease: 'power3.out', clearProps: 'opacity,transform' });
    }

    function end(): void {
        if (!active) return;
        active = false;
        window.clearTimeout(showTimer);
        window.clearTimeout(badgeTimer);
        crawl?.kill();
        crawl = null;
        const main = content();
        main?.removeAttribute('aria-busy');
        if (!gsap) return;
        const g = gsap;
        if (pageVisit && main && !g.isTweening(main)) g.to(main, { opacity: 1, duration: 0.2, clearProps: 'opacity' }); // cancelled / errors: undim
        if (bar.value && g.getProperty(bar.value, 'opacity') !== 0) {
            g.timeline()
                .to(bar.value, { scaleX: 1, duration: 0.2, ease: 'power2.in' })
                .to(bar.value, { autoAlpha: 0, duration: 0.25 })
                .set(bar.value, { scaleX: 0 });
        }
        if (badge.value && badgeVisible.value) {
            g.to(badge.value, { autoAlpha: 0, y: -6, duration: 0.2, onComplete: () => (badgeVisible.value = false) });
        }
    }

    /** A new visit interrupted the previous one: reset instantly. */
    function finishNow(): void {
        window.clearTimeout(showTimer);
        window.clearTimeout(badgeTimer);
        crawl?.kill();
        crawl = null;
        active = false;
    }

    const offs: Array<() => void> = [];
    onMounted(() => {
        void import('gsap').then((m) => (gsap = m.gsap));
        offs.push(
            router.on('start', (event) => begin(event.detail.visit as unknown as Visit)),
            router.on('navigate', entered),
            router.on('finish', end),
        );
    });
    onBeforeUnmount(() => {
        finishNow();
        offs.forEach((off) => off());
    });

    return { badgeVisible };
}
