<script setup lang="ts">
import { ref } from 'vue';
import BrandMarquee from '@/Components/Storefront/Home/BrandMarquee.vue';
import CategoryRail from '@/Components/Storefront/Home/CategoryRail.vue';
import HeroSection from '@/Components/Storefront/Home/HeroSection.vue';
import OffersSection from '@/Components/Storefront/Home/OffersSection.vue';
import ProductRailSection from '@/Components/Storefront/Home/ProductRailSection.vue';
import SpotlightSection from '@/Components/Storefront/Home/SpotlightSection.vue';
import UspStrip from '@/Components/Storefront/Home/UspStrip.vue';
import ProductGrid from '@/Components/Storefront/ProductGrid.vue';
import SectionHeading from '@/Components/Storefront/SectionHeading.vue';
import { revealOnScroll, useGsap } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';
import type { CategoryCard, ProductCard } from '@/types';

/** Same sections as the original store, in the same order — redesigned. */
defineProps<{
    hero: { title: string; subtitle: string; image: { src: string; srcset: string } | null };
    brands: { id: number; name: string; url: string; logo: string | null }[];
    categories: CategoryCard[];
    featured: ProductCard[];
    spotlight: { title: string; text: string | null; url: string; images: string[]; products: ProductCard[] } | null;
    bestSellers: { is_sales_based: boolean; products: ProductCard[] };
    offers: ProductCard[];
}>();

const { t } = useTrans();
const root = ref<HTMLElement | null>(null);
useGsap(root, ({ root: el }) => revealOnScroll(el));
</script>

<template>
    <div ref="root">
        <HeroSection v-bind="hero" />
        <UspStrip />
        <BrandMarquee :brands="brands" />
        <CategoryRail :categories="categories" />

        <section class="container-store mt-24">
            <SectionHeading data-reveal :title="t('storefront.featured')" :link="route('storefront.shop')" :link-label="t('storefront.view_all')" />
            <ProductGrid :products="featured" reveal class="mt-8" />
        </section>

        <SpotlightSection v-if="spotlight" :spotlight="spotlight" />

        <ProductRailSection
            :title="bestSellers.is_sales_based ? t('storefront.best_sellers') : t('storefront.picked_for_you')"
            :products="bestSellers.products"
            :link="route('storefront.shop')"
            :link-label="t('storefront.view_all')"
        />

        <OffersSection :products="offers" />
    </div>
</template>
