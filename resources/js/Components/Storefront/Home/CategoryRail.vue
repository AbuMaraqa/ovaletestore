<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ResponsiveImage from '@/Components/Common/ResponsiveImage.vue';
import Icon from '@/Components/Storefront/Icon.vue';
import SectionHeading from '@/Components/Storefront/SectionHeading.vue';
import { useTrans } from '@/Composables/useTrans';
import type { CategoryCard } from '@/types';

/**
 * "Shop by category": a photo per category (rounded, on sand while it loads) with the name and product count
 * underneath. No card boxes; on hover the photo zooms slightly and the name takes the brand colour.
 * Desktop: two rows of seven (13 categories + "all categories"). Phones/tablets: one swipeable row.
 */
const props = defineProps<{ categories: CategoryCard[] }>();
const { t } = useTrans();

const SHOWN = 13;
const shown = computed(() => props.categories.slice(0, SHOWN));
const tile = 'group flex w-32 shrink-0 snap-start flex-col text-center sm:w-36 lg:w-auto';
</script>

<template>
    <section id="categories" class="container-store mt-20 scroll-mt-32 lg:mt-24">
        <SectionHeading
            data-reveal
            :title="t('storefront.shop_by_category')"
            :link="route('storefront.categories')"
            :link-label="t('storefront.all_categories')"
        />

        <div
            class="-mx-4 mt-8 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 [scrollbar-width:none] sm:gap-4 lg:mx-0 lg:grid lg:grid-cols-7 lg:gap-x-5 lg:gap-y-8 lg:overflow-visible lg:px-0 lg:pb-0 [&::-webkit-scrollbar]:hidden"
            role="list"
            :aria-label="t('storefront.categories')"
        >
            <Link
                v-for="category in shown"
                :key="category.id"
                :href="category.url"
                data-reveal
                role="listitem"
                :class="tile"
            >
                <span class="block aspect-square overflow-hidden rounded-2xl bg-sand">
                    <ResponsiveImage
                        :image="category.image"
                        alt=""
                        sizes="(min-width: 1024px) 170px, 136px"
                        img-class="size-full object-cover transition duration-700 ease-out group-hover:scale-110"
                    />
                </span>
                <span class="flex flex-col items-center px-1 pt-3">
                    <span class="line-clamp-2 text-sm leading-snug font-bold text-cocoa transition group-hover:text-brand-700">{{ category.name }}</span>
                    <span v-if="category.products_count" class="mt-1 text-xs text-stone-500">
                        {{ t('storefront.products_count', { count: category.products_count }) }}
                    </span>
                </span>
            </Link>

            <!-- Last tile: every category. -->
            <Link :href="route('storefront.categories')" data-reveal role="listitem" :class="tile">
                <span class="flex aspect-square items-center justify-center rounded-2xl bg-cocoa transition duration-300 group-hover:bg-brand-800">
                    <span class="flex size-14 items-center justify-center rounded-full bg-white text-cocoa transition duration-300 group-hover:scale-105">
                        <Icon name="arrow" class="size-6 rtl:-scale-x-100" />
                    </span>
                </span>
                <span class="flex flex-col items-center px-1 pt-3">
                    <span class="text-sm leading-snug font-bold text-cocoa group-hover:text-brand-700">{{ t('storefront.all_categories') }}</span>
                    <span v-if="categories.length > SHOWN" class="mt-1 text-xs text-stone-500">{{ t('storefront.more_categories', { count: categories.length - SHOWN }) }}</span>
                </span>
            </Link>
        </div>
    </section>
</template>
