import { router } from '@inertiajs/vue3';

declare global {
    interface Window {
        gtag?: (...args: unknown[]) => void;
    }
}

/**
 * GA4 page views for an Inertia SPA. gtag.js is printed by app.blade.php only on storefront pages, only when a
 * Measurement ID is set (Admin → Settings → SEO) and tracking is enabled (config analytics.tracking), with
 * `send_page_view: false` — so every visit, including the first, is sent from here exactly once.
 *
 * `navigate` also fires for partial reloads (deferred props, polling), hence the de-duplication by URL.
 */
let lastUrl: string | null = null;

function titleOf(seo: unknown): string {
    const tag = Array.isArray(seo) ? seo.find((t): t is string => typeof t === 'string' && t.startsWith('<title')) : undefined;
    // The server-built <title> of the page being shown (the DOM title is updated a tick later).
    return tag ? (new DOMParser().parseFromString(tag, 'text/html').title || document.title) : document.title;
}

export function trackPageViews(): void {
    router.on('navigate', (event) => {
        const page = event.detail.page;
        if (typeof window.gtag !== 'function' || !page.component.startsWith('Storefront/')) return;

        const url = window.location.href;
        if (url === lastUrl) return;
        lastUrl = url;

        window.gtag('event', 'page_view', {
            page_location: url,
            page_title: titleOf(page.props.seo),
            language: document.documentElement.lang,
        });
    });
}
