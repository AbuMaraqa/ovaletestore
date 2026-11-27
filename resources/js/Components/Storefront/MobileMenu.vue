<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, watch } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import SocialIcon from '@/Components/Storefront/SocialIcon.vue';
import { useCurrency } from '@/Composables/useCurrency';
import { useLocale } from '@/Composables/useLocale';
import { lockScroll } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

/**
 * Phone menu, the way the big stores do it: account on top, then a short list of large rows with a chevron
 * (categories are one row that opens the categories page — never a long list here), and the practical things at
 * the bottom: WhatsApp / call, language and currency.
 */
const open = defineModel<boolean>('open', { required: true });

const { t } = useTrans();
const { current, available } = useLocale();
const currency = useCurrency();
const page = usePage<SharedProps>();
const site = computed(() => page.props.site);
const user = computed(() => page.props.auth.user);

const links = computed(() => [
    { label: t('common.home'), href: route('storefront.home'), active: route().current('storefront.home') },
    { label: t('storefront.products'), href: route('storefront.shop'), active: route().current('storefront.shop') || route().current('storefront.product') },
    { label: t('storefront.categories_nav'), href: route('storefront.categories'), active: route().current('storefront.categories') || route().current('storefront.category') },
    { label: t('storefront.brands'), href: route('storefront.brands'), active: route().current('storefront.brands') || route().current('storefront.brand') },
    { label: t('storefront.our_story'), href: route('storefront.about'), active: route().current('storefront.about') },
    { label: t('storefront.contact_us'), href: route('storefront.contact'), active: route().current('storefront.contact') },
]);

watch(open, (value) => {
    document.documentElement.classList.toggle('overflow-hidden', value);
    lockScroll(value);
});
const off = router.on('start', () => (open.value = false));
onBeforeUnmount(() => {
    off();
    document.documentElement.classList.remove('overflow-hidden');
});
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150" leave-to-class="opacity-0">
            <div v-if="open" class="fixed inset-0 z-50 bg-stone-900/40 lg:hidden" @click="open = false" />
        </Transition>
        <!-- Drawer slides in from the inline-start edge (right in RTL, left in LTR). -->
        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="-translate-x-full rtl:translate-x-full"
            leave-active-class="transition-transform duration-200 ease-in"
            leave-to-class="-translate-x-full rtl:translate-x-full"
        >
            <aside
                v-if="open"
                class="fixed inset-y-0 start-0 z-50 flex w-[88vw] max-w-sm flex-col bg-cream shadow-2xl lg:hidden"
                role="dialog"
                aria-modal="true"
                :aria-label="t('common.menu')"
                @keydown.esc="open = false"
            >
                <div class="flex h-16 shrink-0 items-center justify-between px-5">
                    <Link :href="route('storefront.home')" class="block size-12 overflow-hidden" :aria-label="site.name">
                        <img v-if="site.logo" :src="site.logo" :alt="site.name" class="size-full scale-[1.2] object-contain" width="48" height="48" />
                        <span v-else class="font-extrabold">{{ site.name }}</span>
                    </Link>
                    <button type="button" class="-me-2 inline-flex size-11 items-center justify-center rounded-full hover:bg-sand" :aria-label="t('common.close')" @click="open = false">
                        <Icon name="close" class="size-6" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 pb-6" data-lenis-prevent>
                    <!-- Account -->
                    <Link
                        :href="user ? route('account.dashboard') : route('login')"
                        class="mt-1 flex items-center gap-3 rounded-2xl bg-sand px-4 py-3.5 transition hover:bg-sand-deep"
                    >
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-cocoa text-white">
                            <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true"><path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z" /></svg>
                        </span>
                        <span class="min-w-0 flex-1 truncate font-bold text-cocoa">{{ user ? user.name : t('common.login') }}</span>
                        <Icon name="chevron" class="size-4 text-stone-400 rtl:-scale-x-100" />
                    </Link>

                    <!-- Main navigation -->
                    <nav class="mt-4" :aria-label="t('storefront.main_navigation')">
                        <ul class="divide-y divide-hairline">
                            <li v-for="link in links" :key="link.href">
                                <Link
                                    :href="link.href"
                                    :class="['flex items-center justify-between py-4 text-lg font-bold transition', link.active ? 'text-brand-700' : 'text-cocoa hover:text-brand-700']"
                                    :aria-current="link.active ? 'page' : undefined"
                                >
                                    {{ link.label }}
                                    <Icon name="chevron" class="size-4 text-stone-400 rtl:-scale-x-100" />
                                </Link>
                            </li>
                        </ul>
                    </nav>

                    <!-- Reach us -->
                    <div v-if="site.whatsapp || site.phone" class="mt-6 grid grid-cols-2 gap-2">
                        <a
                            v-if="site.whatsapp"
                            :href="`https://wa.me/${site.whatsapp}`"
                            target="_blank"
                            rel="noopener"
                            :class="['btn h-12 bg-whatsapp text-white hover:bg-whatsapp-dark', !site.phone && 'col-span-2']"
                        >
                            <SocialIcon name="whatsapp" class="size-5" /> {{ t('storefront.whatsapp') }}
                        </a>
                        <a v-if="site.phone" :href="`tel:${site.phone}`" :class="['btn-ghost h-12', !site.whatsapp && 'col-span-2']">
                            <SocialIcon name="phone" class="size-4" /> {{ t('storefront.call_us') }}
                        </a>
                    </div>
                </div>

                <!-- Language + currency -->
                <div class="shrink-0 space-y-3 border-t border-hairline px-5 py-4">
                    <div class="grid grid-cols-3 gap-1 rounded-full bg-sand p-1" role="group" :aria-label="t('common.language')">
                        <a
                            v-for="locale in available"
                            :key="locale.code"
                            :href="locale.url"
                            :lang="locale.code"
                            :class="['flex h-10 items-center justify-center rounded-full text-sm font-semibold transition', locale.code === current ? 'bg-white text-cocoa shadow-card' : 'text-stone-600 hover:text-cocoa']"
                            :aria-current="locale.code === current ? 'true' : undefined"
                        >
                            {{ locale.native }}
                        </a>
                    </div>
                    <div v-if="currency.options.value.length" class="grid auto-cols-fr grid-flow-col gap-1 rounded-full bg-sand p-1" role="group" :aria-label="t('storefront.currency')">
                        <button
                            v-for="c in currency.options.value"
                            :key="c.code"
                            type="button"
                            :class="['flex h-10 items-center justify-center rounded-full text-sm font-semibold transition', c.code === currency.current.value ? 'bg-white text-cocoa shadow-card' : 'text-stone-600 hover:text-cocoa']"
                            :aria-pressed="c.code === currency.current.value"
                            dir="ltr"
                            @click="currency.choose(c.code)"
                        >
                            {{ c.symbol }} {{ c.code }}
                        </button>
                    </div>
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>
