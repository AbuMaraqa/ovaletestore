<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ResponsiveImage from '@/Components/Common/ResponsiveImage.vue';
import Icon from '@/Components/Storefront/Icon.vue';
import SectionHeading from '@/Components/Storefront/SectionHeading.vue';
import { useTrans } from '@/Composables/useTrans';
import type { CategoryCard } from '@/types';

/**
 * "Shop by category" circles. Desktop: two tidy rows of seven (13 categories + "all categories"), no
 * arrows needed. Phones/tablets: one swipeable row. Each circle has a brand-gradient "story" ring that
 * warms up on hover, and shows the product count under the name.
 */
const props = defineProps<{ categories: CategoryCard[] }>();
const { t } = useTrans();

const SHOWN = 13;
const shown = computed(() => props.categories.slice(0, SHOWN));
</script>

<template>
    <section id="categories" class="container-store mt-20 scroll-mt-32">
        <SectionHeading data-reveal :title="t('storefront.shop_by_category')" :link="route('storefront.categories')" :link-label="t('storefront.all_categories')" />

        <div
            class="-mx-4 mt-8 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-3 [scrollbar-width:none] sm:gap-6 lg:mx-0 lg:grid lg:grid-cols-7 lg:gap-x-4 lg:gap-y-9 lg:overflow-visible lg:px-0 lg:pb-0 [&::-webkit-scrollbar]:hidden"
            role="list"
            :aria-label="t('storefront.categories')"
        >
            <Link
                v-for="category in shown"
                :key="category.id"
                :href="category.url"
                data-reveal
                role="listitem"
                class="group flex w-24 shrink-0 snap-start flex-col items-center gap-2.5 text-center sm:w-28 lg:w-auto"
            >
                <!-- Gradient ring → white gap → photo. -->
                <span
                    class="rounded-full bg-gradient-to-tr from-brand-300 via-[#e2c58a] to-brand-200 p-[3px] transition duration-300 group-hover:from-brand-600 group-hover:via-brand-400 group-hover:to-[#c9a24d] group-hover:shadow-lift group-focus-visible:from-brand-600 group-focus-visible:to-[#c9a24d]"
                >
                    <span class="block rounded-full bg-cream p-[3px]">
                        <span class="block size-[5.25rem] overflow-hidden rounded-full bg-brand-50 sm:size-24 lg:size-[7.25rem]">
                            <ResponsiveImage
                                :image="category.image"
                                alt=""
                                sizes="(min-width: 1024px) 116px, 96px"
                                img-class="size-full object-cover transition duration-500 ease-out group-hover:scale-110"
                            />
                        </span>
                    </span>
                </span>
                <span class="flex flex-col items-center">
                    <span class="line-clamp-2 text-[13px] leading-snug font-bold text-cocoa transition group-hover:text-brand-700 sm:text-sm">{{ category.name }}</span>
                    <span v-if="category.products_count" class="mt-0.5 text-xs text-stone-500">
                        {{ t('storefront.products_count', { count: category.products_count }) }}
                    </span>
                </span>
            </Link>

            <!-- Last circle: every category. -->
            <Link
                :href="route('storefront.categories')"
                data-reveal
                role="listitem"
                class="group flex w-24 shrink-0 snap-start flex-col items-center gap-2.5 text-center sm:w-28 lg:w-auto"
            >
                <span class="rounded-full bg-gradient-to-tr from-cocoa to-brand-800 p-[3px] transition duration-300 group-hover:shadow-lift">
                    <span class="block rounded-full bg-cream p-[3px]">
                        <span class="flex size-[5.25rem] items-center justify-center rounded-full bg-cocoa text-white transition duration-300 group-hover:bg-brand-800 sm:size-24 lg:size-[7.25rem]">
                            <Icon name="arrow" class="size-7 transition duration-300 group-hover:translate-x-1 rtl:-scale-x-100 rtl:group-hover:-translate-x-1" />
                        </span>
                    </span>
                </span>
                <span class="flex flex-col items-center">
                    <span class="text-[13px] leading-snug font-bold text-cocoa group-hover:text-brand-700 sm:text-sm">{{ t('storefront.all_categories') }}</span>
                    <span v-if="categories.length > SHOWN" class="mt-0.5 text-xs text-stone-500">{{ t('storefront.more_categories', { count: categories.length - SHOWN }) }}</span>
                </span>
            </Link>
        </div>
    </section>
</template>
