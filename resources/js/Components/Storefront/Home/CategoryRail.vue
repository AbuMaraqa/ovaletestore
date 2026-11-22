<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ResponsiveImage from '@/Components/Common/ResponsiveImage.vue';
import ScrollRail from '@/Components/Storefront/ScrollRail.vue';
import SectionHeading from '@/Components/Storefront/SectionHeading.vue';
import { useTrans } from '@/Composables/useTrans';
import type { CategoryCard } from '@/types';

defineProps<{ categories: CategoryCard[] }>();
const { t } = useTrans();
</script>

<template>
    <section id="categories" class="container-store mt-24 scroll-mt-32">
        <SectionHeading data-reveal :title="t('storefront.categories')" :link="route('storefront.shop')" :link-label="t('storefront.view_all')" />
        <ScrollRail :label="t('storefront.categories')" class="mt-8">
            <Link
                v-for="category in categories"
                :key="category.id"
                :href="category.url"
                data-reveal
                class="group relative aspect-[3/4] w-[44%] shrink-0 snap-start overflow-hidden rounded-[1.75rem] bg-brand-100 sm:w-[30%] lg:w-[18%]"
            >
                <ResponsiveImage
                    :image="category.image"
                    :alt="category.name"
                    sizes="(min-width: 1024px) 18vw, (min-width: 640px) 30vw, 44vw"
                    img-class="absolute inset-0 size-full object-cover transition duration-700 ease-out group-hover:scale-110"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-cocoa/85 via-cocoa/20 to-transparent" aria-hidden="true" />
                <div class="absolute inset-x-0 bottom-0 p-4">
                    <p class="line-clamp-2 text-base font-bold leading-snug text-white">{{ category.name }}</p>
                    <p class="mt-1 text-xs text-white/70 tabular-nums">{{ t('storefront.products_count', { count: category.products_count ?? 0 }) }}</p>
                </div>
            </Link>
        </ScrollRail>
    </section>
</template>
