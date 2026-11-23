import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, defineAsyncComponent, h, type DefineComponent } from 'vue';
import { ZiggyVue } from 'ziggy-js';

const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue');

// Layouts are code-split: storefront visitors never download admin layout code.
const AdminLayout = defineAsyncComponent(() => import('./Layouts/AdminLayout.vue'));
const AuthLayout = defineAsyncComponent(() => import('./Layouts/AuthLayout.vue'));
const StorefrontLayout = defineAsyncComponent(() => import('./Layouts/StorefrontLayout.vue'));
const PosLayout = defineAsyncComponent(() => import('./Layouts/PosLayout.vue'));

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
    progress: { color: '#b4542a', delay: 150, showSpinner: false },
    nonce,
});
