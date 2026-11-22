<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import LanguageSwitcher from '@/Components/Storefront/LanguageSwitcher.vue';
import MobileMenu from '@/Components/Storefront/MobileMenu.vue';
import SearchForm from '@/Components/Storefront/SearchForm.vue';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps & { nav_categories: { id: number; name: string; url: string }[] }>();
const { t } = useTrans();

const site = computed(() => page.props.site);
const categories = computed(() => page.props.nav_categories ?? []);
const cartCount = computed(() => page.props.cart_count);
const megaOpen = ref(false);
const mobileOpen = ref(false);
</script>

<template>
    <header class="sticky top-0 z-40">
        <div class="bg-cocoa text-xs text-white/85">
            <div class="container-store flex h-9 items-center justify-center gap-2 sm:justify-between">
                <p class="truncate">{{ site.tagline }}</p>
                <a v-if="site.phone" :href="`tel:${site.phone}`" class="hidden font-medium tabular-nums hover:text-white sm:block" dir="ltr">{{ site.phone }}</a>
            </div>
        </div>

        <div class="border-b border-stone-200/70 bg-cream/90 backdrop-blur-md supports-[backdrop-filter]:bg-cream/75">
            <div class="container-store flex h-16 items-center gap-3 lg:h-20 lg:gap-8">
                <button type="button" class="-ms-2 inline-flex size-11 items-center justify-center rounded-full hover:bg-stone-100 lg:hidden" :aria-label="t('common.menu')" @click="mobileOpen = true">
                    <Icon name="menu" class="size-6" />
                </button>

                <Link :href="route('storefront.home')" class="flex shrink-0 items-center gap-2" :aria-label="site.name">
                    <img v-if="site.logo" :src="site.logo" :alt="site.name" class="h-10 w-auto lg:h-12" width="48" height="48" />
                    <span class="text-lg font-bold tracking-tight lg:text-xl">{{ site.name }}</span>
                </Link>

                <nav class="hidden items-center gap-1 lg:flex" @mouseleave="megaOpen = false">
                    <Link :href="route('storefront.shop')" class="rounded-full px-4 py-2 text-sm font-medium hover:bg-stone-100">{{ t('storefront.shop') }}</Link>
                    <div class="relative">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full px-4 py-2 text-sm font-medium hover:bg-stone-100"
                            :aria-expanded="megaOpen"
                            aria-haspopup="true"
                            @click="megaOpen = !megaOpen"
                            @mouseenter="megaOpen = true"
                        >
                            {{ t('storefront.categories') }}
                            <Icon name="chevron" :class="['size-4 transition-transform', megaOpen ? 'rotate-90' : 'rtl:-scale-x-100']" />
                        </button>
                        <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0 -translate-y-1" leave-active-class="transition duration-100" leave-to-class="opacity-0">
                            <div v-if="megaOpen" class="absolute start-0 top-full w-[min(56rem,calc(100vw-4rem))] pt-3">
                                <div class="grid grid-cols-3 gap-1 rounded-3xl bg-white p-4 shadow-lift ring-1 ring-stone-100">
                                    <Link
                                        v-for="category in categories"
                                        :key="category.id"
                                        :href="category.url"
                                        class="rounded-xl px-3 py-2.5 text-sm text-stone-700 transition hover:bg-brand-50 hover:text-brand-800"
                                        @click="megaOpen = false"
                                    >
                                        {{ category.name }}
                                    </Link>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </nav>

                <div class="hidden flex-1 lg:block">
                    <SearchForm />
                </div>

                <div class="ms-auto flex items-center gap-1 lg:ms-0">
                    <LanguageSwitcher />
                    <Link
                        :href="route('storefront.cart')"
                        class="relative inline-flex size-11 items-center justify-center rounded-full hover:bg-stone-100"
                        :aria-label="`${t('cart.title')} (${cartCount})`"
                    >
                        <Icon name="cart" class="size-6" />
                        <span
                            v-if="cartCount > 0"
                            class="absolute end-0.5 top-0.5 flex min-w-5 items-center justify-center rounded-full bg-brand-600 px-1 text-[11px] font-bold leading-5 text-white tabular-nums"
                        >
                            {{ cartCount > 99 ? '99+' : cartCount }}
                        </span>
                    </Link>
                </div>
            </div>
            <div class="container-store pb-3 lg:hidden">
                <SearchForm />
            </div>
        </div>

        <MobileMenu v-model:open="mobileOpen" :categories="categories" />
    </header>
</template>
