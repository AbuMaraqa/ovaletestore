<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import Icon from '@/Components/Storefront/Icon.vue';
import MobileMenu from '@/Components/Storefront/MobileMenu.vue';
import SearchOverlay from '@/Components/Storefront/SearchOverlay.vue';
import SocialIcon from '@/Components/Storefront/SocialIcon.vue';
import { onClickOutside } from '@/Composables/onClickOutside';
import { useCurrency } from '@/Composables/useCurrency';
import { useLocale } from '@/Composables/useLocale';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

/** Header modelled on the original store: dark top bar (slogan · language · currency · socials) + white main bar. */
const page = usePage<SharedProps & { nav_categories: { id: number; name: string; url: string }[] }>();
const { t } = useTrans();
const { current, available } = useLocale();
const currency = useCurrency();

const site = computed(() => page.props.site);
const user = computed(() => page.props.auth.user);
const categories = computed(() => page.props.nav_categories ?? []);
const cartCount = computed(() => page.props.cart_count);
const socials = computed(() => Object.entries(site.value.social ?? {}));

const searchOpen = ref(false);
const mobileOpen = ref(false);
const openMenu = ref<'lang' | 'currency' | 'categories' | 'about' | 'account' | null>(null);
const header = ref<HTMLElement | null>(null);
onClickOutside(header, () => (openMenu.value = null));
const toggle = (menu: typeof openMenu.value) => (openMenu.value = openMenu.value === menu ? null : menu);

const nav = computed(() => [
    { label: t('common.home'), href: route('storefront.home'), active: route().current('storefront.home') },
    { label: t('storefront.products'), href: route('storefront.shop'), active: route().current('storefront.shop') || route().current('storefront.product') },
]);
const isActive = (name: string) => route().current(name);
const logout = () => router.post(route('logout'));
</script>

<template>
    <header ref="header" class="sticky top-0 z-40">
        <!-- Top bar -->
        <div class="bg-[#2b160f] text-[13px] text-white/85">
            <div class="container-store flex h-10 items-center justify-between gap-4">
                <p class="flex min-w-0 items-center gap-2 font-semibold">
                    <span class="size-2.5 shrink-0 rounded-full bg-[#c9a24d] ring-2 ring-[#c9a24d]/30" aria-hidden="true" />
                    <span class="truncate">{{ site.tagline }}</span>
                </p>
                <div class="hidden items-center gap-4 md:flex">
                    <div class="relative">
                        <button type="button" class="inline-flex items-center gap-1.5 hover:text-white" :aria-expanded="openMenu === 'lang'" @click="toggle('lang')">
                            <Icon name="globe" class="size-4" />
                            {{ available.find((l) => l.code === current)?.native }}
                            <Icon name="chevron" class="size-3 rotate-90" />
                        </button>
                        <ul v-if="openMenu === 'lang'" class="absolute end-0 top-full z-50 mt-2 w-40 rounded-xl bg-white p-1.5 text-cocoa shadow-lift ring-1 ring-stone-100">
                            <li v-for="l in available" :key="l.code">
                                <a :href="l.url" :lang="l.code" :class="['flex items-center justify-between rounded-lg px-3 py-2 hover:bg-stone-50', l.code === current && 'font-bold text-brand-700']">
                                    {{ l.native }} <Icon v-if="l.code === current" name="check" class="size-4" />
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div v-if="currency.options.value.length" class="relative">
                        <button type="button" class="inline-flex items-center gap-1.5 hover:text-white" :aria-expanded="openMenu === 'currency'" @click="toggle('currency')">
                            {{ t('storefront.currency') }} <b class="font-bold text-white" dir="ltr">{{ site.currency }}</b>
                            <Icon name="chevron" class="size-3 rotate-90" />
                        </button>
                        <ul v-if="openMenu === 'currency'" class="absolute end-0 top-full z-50 mt-2 w-52 rounded-xl bg-white p-1.5 text-cocoa shadow-lift ring-1 ring-stone-100">
                            <li v-for="c in currency.options.value" :key="c.code">
                                <button
                                    type="button"
                                    :class="['flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-start hover:bg-stone-50', c.code === site.currency && 'font-bold text-brand-700']"
                                    @click="currency.choose(c.code); openMenu = null"
                                >
                                    <span>{{ c.label }}</span>
                                    <span class="inline-flex items-center gap-1.5 text-xs text-stone-500" dir="ltr">{{ c.symbol }} {{ c.code }}<Icon v-if="c.code === site.currency" name="check" class="size-4 text-brand-700" /></span>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <span v-else class="inline-flex items-center gap-1.5">{{ t('storefront.currency') }} <b class="font-bold text-white" dir="ltr">{{ site.currency }}</b></span>
                    <span v-if="socials.length || site.phone" class="h-4 w-px bg-white/20" aria-hidden="true" />
                    <div class="flex items-center gap-3.5 text-[#e4cfa3]">
                        <a v-for="[network, url] in socials" :key="network" :href="url" target="_blank" rel="noopener" :aria-label="network" class="transition hover:scale-110 hover:text-white">
                            <SocialIcon :name="network" class="size-4" />
                        </a>
                        <a v-if="site.whatsapp" :href="`https://wa.me/${site.whatsapp}`" target="_blank" rel="noopener" aria-label="WhatsApp" class="transition hover:scale-110 hover:text-white"><SocialIcon name="whatsapp" class="size-4" /></a>
                        <a v-if="site.phone" :href="`tel:${site.phone}`" :aria-label="site.phone" class="transition hover:scale-110 hover:text-white"><SocialIcon name="phone" class="size-4" /></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main bar -->
        <div class="border-b border-stone-200/70 bg-white/95 backdrop-blur-md">
            <div class="container-store flex h-[4.5rem] items-center gap-4 lg:h-[5.25rem] lg:gap-10">
                <button type="button" class="-ms-2 inline-flex size-11 items-center justify-center rounded-full hover:bg-stone-100 lg:hidden" :aria-label="t('common.menu')" @click="mobileOpen = true">
                    <Icon name="menu" class="size-6" />
                </button>

                <Link :href="route('storefront.home')" class="shrink-0" :aria-label="site.name">
                    <!-- The logo file has wide empty margins: a clipped, slightly zoomed box shows the mark at full size. -->
                    <span v-if="site.logo" class="block size-16 overflow-hidden lg:size-20">
                        <img :src="site.logo" :alt="site.name" class="size-full scale-[1.2] object-contain" width="80" height="80" fetchpriority="high" />
                    </span>
                    <span v-else class="text-xl font-extrabold">{{ site.name }}</span>
                </Link>

                <nav class="hidden items-center gap-8 text-[15px] font-semibold text-cocoa lg:flex" :aria-label="t('storefront.main_navigation')">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        :class="['relative py-2 transition hover:text-brand-700', item.active && 'text-cocoa after:absolute after:inset-x-0 after:-bottom-0.5 after:h-0.5 after:rounded-full after:bg-[#c9a24d]']"
                    >
                        {{ item.label }}
                    </Link>

                    <div class="relative">
                        <button
                            type="button"
                            :class="['relative inline-flex items-center gap-1 py-2 transition hover:text-brand-700', (isActive('storefront.category') || isActive('storefront.categories')) && 'after:absolute after:inset-x-0 after:-bottom-0.5 after:h-0.5 after:rounded-full after:bg-[#c9a24d]']"
                            :aria-expanded="openMenu === 'categories'"
                            @click="toggle('categories')"
                        >
                            {{ t('storefront.categories_nav') }}
                        </button>
                        <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0 -translate-y-1" leave-active-class="transition duration-100" leave-to-class="opacity-0">
                            <div v-if="openMenu === 'categories'" class="absolute start-0 top-full z-50 w-[min(52rem,calc(100vw-3rem))] pt-4">
                                <div class="grid grid-cols-3 gap-1 rounded-3xl bg-white p-4 shadow-lift ring-1 ring-stone-100" data-lenis-prevent>
                                    <Link v-for="c in categories" :key="c.id" :href="c.url" class="rounded-xl px-3 py-2.5 text-sm font-medium text-stone-700 hover:bg-brand-50 hover:text-brand-800" @click="openMenu = null">
                                        {{ c.name }}
                                    </Link>
                                    <Link :href="route('storefront.categories')" class="col-span-3 mt-2 inline-flex items-center justify-center gap-2 rounded-xl bg-brand-50 px-3 py-3 text-sm font-bold text-brand-800 hover:bg-brand-100" @click="openMenu = null">
                                        {{ t('storefront.all_categories') }}
                                        <Icon name="arrow" class="size-4 rtl:-scale-x-100" />
                                    </Link>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <Link :href="route('storefront.contact')" :class="['relative py-2 transition hover:text-brand-700', isActive('storefront.contact') && 'after:absolute after:inset-x-0 after:-bottom-0.5 after:h-0.5 after:rounded-full after:bg-[#c9a24d]']">
                        {{ t('storefront.contact_us') }}
                    </Link>

                    <div class="relative">
                        <button type="button" class="inline-flex items-center gap-1 py-2 transition hover:text-brand-700" :aria-expanded="openMenu === 'about'" @click="toggle('about')">
                            {{ t('storefront.about_us') }} <Icon name="chevron" class="size-3.5 rotate-90" />
                        </button>
                        <ul v-if="openMenu === 'about'" class="absolute start-0 top-full z-50 mt-3 w-52 rounded-2xl bg-white p-1.5 text-sm shadow-lift ring-1 ring-stone-100">
                            <li><Link :href="route('storefront.about')" class="block rounded-xl px-3 py-2.5 hover:bg-brand-50" @click="openMenu = null">{{ t('storefront.our_story') }}</Link></li>
                            <li><Link :href="route('storefront.brands')" class="block rounded-xl px-3 py-2.5 hover:bg-brand-50" @click="openMenu = null">{{ t('storefront.brands') }}</Link></li>
                            <li><Link :href="route('storefront.contact')" class="block rounded-xl px-3 py-2.5 hover:bg-brand-50" @click="openMenu = null">{{ t('storefront.contact_us') }}</Link></li>
                        </ul>
                    </div>
                </nav>

                <div class="ms-auto flex items-center gap-2.5">
                    <!-- Search pill (opens the full-screen search) -->
                    <button
                        type="button"
                        class="hidden h-11 w-56 items-center gap-2.5 rounded-full bg-[#f7efe2] px-4 text-sm text-stone-600 ring-1 ring-[#eadfcb] transition hover:ring-[#c9a24d] md:inline-flex xl:w-64"
                        @click="searchOpen = true"
                    >
                        <Icon name="search" class="size-5 text-cocoa" />
                        {{ t('storefront.search_product') }}
                    </button>
                    <button type="button" class="inline-flex size-11 items-center justify-center rounded-full bg-[#f7efe2] text-cocoa md:hidden" :aria-label="t('common.search')" @click="searchOpen = true">
                        <Icon name="search" class="size-5" />
                    </button>

                    <!-- Account -->
                    <div class="relative">
                        <Link v-if="!user" :href="route('login')" class="inline-flex size-11 items-center justify-center rounded-full bg-[#f7efe2] text-cocoa ring-1 ring-[#eadfcb] hover:text-brand-700" :aria-label="t('common.login')">
                            <svg viewBox="0 0 24 24" class="size-6" fill="currentColor" aria-hidden="true"><path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z" /></svg>
                        </Link>
                        <template v-else>
                            <button type="button" class="block rounded-full" :aria-expanded="openMenu === 'account'" :aria-label="user.name" @click="toggle('account')">
                                <UserAvatar :avatar="user.avatar" :name="user.name" size="size-11" :ring="false" />
                            </button>
                            <div v-if="openMenu === 'account'" class="absolute end-0 top-full z-50 mt-3 w-60 rounded-2xl bg-white p-2 text-sm shadow-lift ring-1 ring-stone-100">
                                <p class="truncate px-3 pb-2 pt-1 font-bold text-cocoa">{{ user.name }}</p>
                                <Link :href="route('account.dashboard')" class="block rounded-xl px-3 py-2.5 hover:bg-brand-50" @click="openMenu = null">{{ t('account.my_account') }}</Link>
                                <Link :href="route('account.orders')" class="block rounded-xl px-3 py-2.5 hover:bg-brand-50" @click="openMenu = null">{{ t('account.my_orders') }}</Link>
                                <Link v-if="user.pos_url" :href="user.pos_url" class="block rounded-xl px-3 py-2.5 font-semibold text-brand-700 hover:bg-brand-50" @click="openMenu = null">{{ t('pos.title') }}</Link>
                                <a v-if="user.admin_url" :href="user.admin_url" class="block rounded-xl px-3 py-2.5 font-semibold text-brand-700 hover:bg-brand-50">{{ t('account.control_panel') }}</a>
                                <button type="button" class="mt-1 block w-full rounded-xl px-3 py-2.5 text-start text-red-600 hover:bg-red-50" @click="logout">{{ t('common.logout') }}</button>
                            </div>
                        </template>
                    </div>

                    <!-- Cart -->
                    <Link :href="route('storefront.cart')" class="relative inline-flex size-11 items-center justify-center rounded-full bg-[#4a2216] text-white transition hover:bg-[#3a190f]" :aria-label="`${t('cart.title')} (${cartCount})`">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M5 8h14l-1 12H6L5 8Z" /><path d="M9 8V6a3 3 0 0 1 6 0v2" /><path d="M9.5 13.5 11 15l3.5-3.5" stroke-linecap="round" /></svg>
                        <span v-if="cartCount > 0" class="absolute -start-1 -top-1 flex min-w-5 items-center justify-center rounded-full bg-[#c9a24d] px-1 text-[11px] font-bold leading-5 text-[#2b160f] tabular-nums ring-2 ring-white">
                            {{ cartCount > 99 ? '99+' : cartCount }}
                        </span>
                    </Link>
                </div>
            </div>
        </div>

        <MobileMenu v-model:open="mobileOpen" :categories="categories" />
        <SearchOverlay v-model:open="searchOpen" />
    </header>
</template>
