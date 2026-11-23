<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ResponsiveImage from '@/Components/Common/ResponsiveImage.vue';
import ScrollRail from '@/Components/Storefront/ScrollRail.vue';
import SectionHeading from '@/Components/Storefront/SectionHeading.vue';
import { useTrans } from '@/Composables/useTrans';
import type { CategoryCard } from '@/types';

/** "Shop by category" circles (compact, many visible at once) — the pattern used by large marketplaces. */
defineProps<{ categories: CategoryCard[] }>();
const { t } = useTrans();
</script>

<template>
    <section id="categories" class="container-store mt-20 scroll-mt-32">
        <SectionHeading data-reveal :title="t('storefront.shop_by_category')" :link="route('storefront.shop')" :link-label="t('storefront.view_all')" />
        <ScrollRail :label="t('storefront.categories')" class="mt-6">
            <Link
                v-for="category in categories"
                :key="category.id"
                :href="category.url"
                data-reveal
                class="group flex w-[5.5rem] shrink-0 snap-start flex-col items-center gap-2 text-center sm:w-24 lg:w-[6.5rem]"
            >
                <span class="relative block size-[4.75rem] overflow-hidden rounded-full bg-brand-50 ring-1 ring-stone-200 transition duration-300 group-hover:ring-2 group-hover:ring-brand-500 sm:size-20 lg:size-[5.5rem]">
                    <ResponsiveImage
                        :image="category.image"
                        :alt="category.name"
                        sizes="96px"
                        img-class="size-full object-cover transition duration-500 group-hover:scale-110"
                    />
                </span>
                <span class="line-clamp-2 text-xs font-semibold leading-snug text-cocoa group-hover:text-brand-700 sm:text-[13px]">{{ category.name }}</span>
            </Link>
        </ScrollRail>
    </section>
</template>
