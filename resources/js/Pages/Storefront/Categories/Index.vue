<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ResponsiveImage from '@/Components/Common/ResponsiveImage.vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { revealOnScroll, useGsap } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';
import type { CategoryCard } from '@/types';

type CategoryTile = CategoryCard & { products_count: number; on_sale_count: number; sale_url: string };

/** All categories as image tiles: quick filter, product count, and a direct link to each category's offers. */
const props = defineProps<{ categories: CategoryTile[] }>();
const { t } = useTrans();
const root = ref<HTMLElement | null>(null);
useGsap(root, ({ root: el }) => revealOnScroll(el));

const query = ref('');
/** Arabic-friendly match: ignore diacritics/tatweel and unify alef / ta marbuta / alef maqsura forms. */
const normalize = (text: string) =>
    text
        .toLowerCase()
        .replace(/[ً-ْـ]/g, '')
        .replace(/[أإآ]/g, 'ا')
        .replace(/ة/g, 'ه')
        .replace(/ى/g, 'ي')
        .trim();
const visible = computed(() => {
    const q = normalize(query.value);
    return q ? props.categories.filter((c) => normalize(c.name).includes(q)) : props.categories;
});
const totalProducts = computed(() => props.categories.reduce((sum, c) => sum + c.products_count, 0));
</script>

<template>
    <div ref="root">
        <div class="border-b border-stone-200/70 bg-gradient-to-b from-brand-50 to-cream">
            <div class="container-store py-10 text-center sm:py-12">
                <h1 class="text-3xl font-extrabold text-cocoa sm:text-4xl">{{ t('storefront.categories_title') }}</h1>
                <p class="mx-auto mt-3 max-w-xl text-base text-stone-600 sm:text-lg">{{ t('storefront.categories_subtitle') }}</p>
                <p class="mt-2 text-sm font-semibold text-brand-700">
                    {{ t('storefront.categories_stats', { categories: categories.length, products: totalProducts }) }}
                </p>

                <label class="relative mx-auto mt-6 block max-w-md">
                    <span class="sr-only">{{ t('storefront.categories_search') }}</span>
                    <Icon name="search" class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-stone-500" />
                    <input
                        v-model="query"
                        type="search"
                        :placeholder="t('storefront.categories_search')"
                        class="h-12 w-full rounded-full border border-stone-200 bg-white ps-12 pe-5 text-base text-cocoa shadow-sm outline-none transition placeholder:text-stone-500 focus:border-brand-400 focus:ring-4 focus:ring-brand-100"
                    />
                </label>
            </div>
        </div>

        <div class="container-store py-10">
            <ul v-if="visible.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4">
                <li v-for="category in visible" :key="category.id" data-reveal>
                    <div class="group relative flex h-full flex-col overflow-hidden rounded-3xl bg-white ring-1 ring-stone-200/80 transition duration-300 hover:-translate-y-1 hover:shadow-lift">
                        <Link :href="category.url" class="relative block aspect-[4/3] overflow-hidden bg-brand-50" tabindex="-1" :aria-label="category.name">
                            <ResponsiveImage
                                :image="category.image"
                                :alt="category.name"
                                sizes="(min-width: 1024px) 24vw, (min-width: 640px) 31vw, 46vw"
                                img-class="size-full object-cover transition duration-500 group-hover:scale-105"
                            />
                        </Link>
                        <!-- Above the card-wide link overlay: goes straight to this category's offers. -->
                        <Link
                            v-if="category.on_sale_count"
                            :href="category.sale_url"
                            class="absolute start-2.5 top-2.5 z-10 rounded-full bg-brand-600 px-2.5 py-1 text-xs font-bold text-white shadow-sm transition hover:bg-brand-700"
                        >
                            {{ t('storefront.category_offers', { count: category.on_sale_count }) }}
                        </Link>
                        <div class="flex flex-1 flex-col p-3.5 sm:p-4">
                            <h2 class="line-clamp-2 text-base font-bold leading-snug text-cocoa sm:text-lg">
                                <Link :href="category.url" class="after:absolute after:inset-0 hover:text-brand-700">{{ category.name }}</Link>
                            </h2>
                            <div class="mt-auto flex items-center justify-between gap-2 pt-2 text-sm">
                                <span class="text-stone-600">{{ t('storefront.products_count', { count: category.products_count }) }}</span>
                                <Icon name="arrow" class="size-4 text-brand-600 transition group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5" />
                            </div>
                        </div>
                    </div>
                </li>
            </ul>

            <div v-else class="mx-auto max-w-md rounded-3xl bg-white p-8 text-center ring-1 ring-stone-200/80">
                <p class="text-lg font-bold text-cocoa">{{ t('storefront.categories_no_match', { q: query }) }}</p>
                <div class="mt-5 flex flex-wrap justify-center gap-3">
                    <button type="button" class="btn h-11 bg-cocoa px-6 text-white hover:bg-brand-700" @click="query = ''">{{ t('storefront.all_categories') }}</button>
                    <Link :href="route('storefront.shop', { q: query })" class="btn h-11 px-6 text-cocoa ring-1 ring-cocoa hover:bg-cocoa hover:text-white">
                        {{ t('storefront.search_products_for', { q: query }) }}
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
