<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

/**
 * Branded error page (404 and friends) in the store layout: a playful "4 🍩 4" made of a glazed donut,
 * a search box, the way back, and the main categories so a lost visitor keeps shopping.
 */
const props = defineProps<{ status: number }>();
const { t } = useTrans();
const page = usePage<SharedProps & { nav_categories?: { id: number; name: string; url: string }[] }>();

const notFound = computed(() => props.status === 404);
const categories = computed(() => (page.props.nav_categories ?? []).slice(0, 8));
const whatsapp = computed(() => page.props.site?.whatsapp ?? null);
const digits = computed(() => String(props.status).split(''));

const q = ref('');
const search = () => router.get(route('storefront.shop'), q.value.trim() ? { q: q.value.trim() } : {});
</script>

<template>
    <section class="relative overflow-hidden">
        <!-- Soft warm glow behind the number. -->
        <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[28rem] bg-[radial-gradient(60%_60%_at_50%_30%,var(--color-brand-100),transparent)]" aria-hidden="true" />

        <div class="container-store flex flex-col items-center py-16 text-center sm:py-24">
            <!-- 4 [donut] 4 (other statuses: plain digits). -->
            <p class="flex items-center gap-2 font-extrabold text-cocoa select-none sm:gap-4" dir="ltr" :aria-label="String(status)">
                <template v-for="(d, i) in digits" :key="i">
                    <svg v-if="notFound && d === '0'" class="error-donut size-28 sm:size-40" viewBox="0 0 120 120" aria-hidden="true">
                        <circle cx="60" cy="60" r="52" fill="#e9c48f" />
                        <path d="M60 14a46 46 0 1 1-40.2 23.6c3.6 6 9.4 2.6 12.4 7.8 2.8 4.8-2.6 9 1.6 13 4.4 4.2 9.6-.6 13.8 3.4 4.4 4.4-.4 10.4 4.6 13.6 5 3.2 9-2.4 14.2.2 5 2.6 3.2 9.6 8.6 10.6 5.2 1 7.6-5.4 12.8-4.8C99 60 106 55 106 60A46 46 0 0 0 60 14Z" fill="#7a3d1f" />
                        <circle cx="60" cy="60" r="16" fill="var(--color-cream)" />
                        <g stroke-linecap="round" stroke-width="4">
                            <path d="M44 32l6 3" stroke="#f4c2c2" /><path d="M74 28l5-4" stroke="#fff3c4" /><path d="M86 46l6 1" stroke="#bfe3d0" />
                            <path d="M34 54l2 6" stroke="#fff3c4" /><path d="M80 70l4 5" stroke="#f4c2c2" /><path d="M58 30l1 6" stroke="#bfe3d0" />
                        </g>
                    </svg>
                    <span v-else class="text-[7rem] leading-none tracking-tight sm:text-[10rem]" aria-hidden="true">{{ d }}</span>
                </template>
            </p>

            <h1 class="mt-6 text-2xl font-extrabold text-cocoa sm:text-3xl">
                {{ notFound ? t('storefront.not_found_title') : t(`http-statuses.${status}`) }}
            </h1>
            <p class="mt-3 max-w-md text-stone-600">{{ notFound ? t('storefront.not_found_text') : t('storefront.error_text') }}</p>

            <form v-if="notFound" class="mt-8 flex w-full max-w-md items-center gap-2 rounded-full bg-white p-1.5 ring-1 ring-stone-200 focus-within:ring-2 focus-within:ring-brand-500" role="search" @submit.prevent="search">
                <Icon name="search" class="ms-3 size-5 shrink-0 text-stone-400" />
                <input v-model="q" type="search" class="min-w-0 flex-1 bg-transparent py-2 text-sm text-cocoa outline-none placeholder:text-stone-400" :placeholder="t('storefront.search_placeholder')" :aria-label="t('storefront.search_placeholder')" />
                <button type="submit" class="btn-primary h-10 px-5 text-sm">{{ t('storefront.search') }}</button>
            </form>

            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <Link :href="route('storefront.home')" class="btn-primary h-11 px-6">{{ t('common.home') }}</Link>
                <Link :href="route('storefront.shop')" class="btn-ghost h-11 px-6">{{ t('storefront.products') }}</Link>
                <a v-if="whatsapp" :href="`https://wa.me/${whatsapp}`" target="_blank" rel="noopener" class="btn-ghost h-11 px-6">{{ t('storefront.ask_on_whatsapp') }}</a>
            </div>

            <div v-if="notFound && categories.length" class="mt-14 w-full max-w-3xl">
                <p class="text-sm font-bold text-stone-500">{{ t('storefront.maybe_looking_for') }}</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <Link v-for="c in categories" :key="c.id" :href="c.url" class="chip hover:bg-cocoa hover:text-white hover:ring-cocoa">{{ c.name }}</Link>
                </div>
            </div>
        </div>
    </section>
</template>

<style>
.error-donut {
    animation: error-donut-float 3.2s ease-in-out infinite;
    filter: drop-shadow(0 14px 18px rgb(122 61 31 / 0.18));
}
@keyframes error-donut-float {
    0%, 100% { transform: translateY(0) rotate(0); }
    50% { transform: translateY(-8px) rotate(-8deg); }
}
@media (prefers-reduced-motion: reduce) {
    .error-donut { animation: none; }
}
</style>
