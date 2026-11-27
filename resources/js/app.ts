import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, defineAsyncComponent, h, type DefineComponent } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { trackPageViews } from './analytics';

const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue');

// Layouts are code-split: storefront visitors never download admin layout code.
const AdminLayout = defineAsyncComponent(() => import('./Layouts/AdminLayout.vue'));
const AuthLayout = defineAsyncComponent(() => import('./Layouts/AuthLayout.vue'));
const StorefrontLayout = defineAsyncComponent(() => import('./Layouts/StorefrontLayout.vue'));
const PosLayout = defineAsyncComponent(() => import('./Layouts/PosLayout.vue'));

trackPageViews(); // GA4 (only active when app.blade.php printed gtag.js)

const nonce = document.querySelector<HTMLMetaElement>('meta[name="csp-nonce"]')?.content;

createInertiaApp({
    resolve: async (name) => {
        const loader = pages[`./Pages/${name}.vue`];
        if (!loader) throw new Error(`Page not found: ${name}`);
        return (await loader()).default;
    },
    layout: (name: string) => {
        if (name.startsWith('Admin/')) return AdminLayout;
        if (name.startsWith('Auth/')) return AuthLayout;
        if (name.startsWith('Pos/')) return PosLayout;
        return StorefrontLayout;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: false, // GSAP loader in every layout: Components/Common/PageLoader.vue
    // <head> tags built on the server by laravel-seo (App\Support\Seo\SeoData → `seo` prop): Inertia keeps
    // them in sync on every visit (matched by their data-inertia keys, so nothing is duplicated or left stale).
    serverHead: 'seo',
    nonce,
});
