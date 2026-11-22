<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ResponsiveImage from '@/Components/Common/ResponsiveImage.vue';
import Icon from '@/Components/Storefront/Icon.vue';
import ProductGrid from '@/Components/Storefront/ProductGrid.vue';
import SectionHeading from '@/Components/Storefront/SectionHeading.vue';
import { useTrans } from '@/Composables/useTrans';
import type { CategoryCard, ProductCard } from '@/types';

defineProps<{ categories: CategoryCard[]; featured: ProductCard[]; newArrivals: ProductCard[] }>();
const { t } = useTrans();

const usps = [
    { icon: 'shield', title: 'storefront.usp_quality', text: 'storefront.usp_quality_text' },
    { icon: 'truck', title: 'storefront.usp_delivery', text: 'storefront.usp_delivery_text' },
    { icon: 'chat', title: 'storefront.usp_support', text: 'storefront.usp_support_text' },
] as const;
</script>

<template>
    <!-- Hero -->
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(60rem_30rem_at_85%_-10%,var(--color-brand-100),transparent),radial-gradient(40rem_24rem_at_0%_110%,oklch(0.93_0.04_150),transparent)]" />
        <div class="container-store grid items-center gap-12 py-14 lg:grid-cols-12 lg:py-24">
            <div class="lg:col-span-6">
                <p class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">
                    <span class="size-1.5 rounded-full bg-brand-500" />
                    {{ t('storefront.hero_eyebrow') }}
                </p>
                <h1 class="mt-6 text-4xl font-bold leading-[1.15] tracking-tight text-balance text-cocoa sm:text-5xl lg:text-6xl">
                    {{ t('storefront.hero_title') }}
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-stone-600">{{ t('storefront.hero_subtitle') }}</p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <Link :href="route('storefront.shop')" class="btn-primary h-12 px-8">
                        {{ t('storefront.hero_cta') }}
                        <Icon name="arrow" class="size-4 rtl:-scale-x-100" />
                    </Link>
                    <a href="#categories" class="btn-ghost h-12 px-8">{{ t('storefront.hero_secondary') }}</a>
                </div>
            </div>
            <div class="lg:col-span-6">
                <div class="grid grid-cols-2 gap-4">
                    <Link
                        v-for="(product, i) in featured.concat(newArrivals).slice(0, 4)"
                        :key="product.id"
                        :href="product.url"
                        :class="['group relative aspect-square overflow-hidden rounded-[2rem] bg-white shadow-card ring-1 ring-stone-100', i % 2 === 1 ? 'translate-y-8' : '']"
                    >
                        <ResponsiveImage :image="product.image" :alt="product.name" :priority="i < 2" sizes="(min-width: 1024px) 22vw, 45vw" img-class="size-full object-contain p-6 transition duration-500 group-hover:scale-105" />
                    </Link>
                </div>
            </div>
        </div>
    </section>

    <!-- USPs -->
    <section class="container-store">
        <ul class="grid gap-4 rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-stone-100 sm:grid-cols-3 sm:p-8">
            <li v-for="usp in usps" :key="usp.title" class="flex items-start gap-4">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700"><Icon :name="usp.icon" class="size-6" /></span>
                <div>
                    <p class="font-semibold text-cocoa">{{ t(usp.title) }}</p>
                    <p class="mt-1 text-sm text-stone-500">{{ t(usp.text) }}</p>
                </div>
            </li>
        </ul>
    </section>

    <!-- Categories -->
    <section id="categories" class="container-store mt-20 scroll-mt-32">
        <SectionHeading :title="t('storefront.shop_by_category')" :link="route('storefront.shop')" :link-label="t('storefront.view_all')" />
        <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
            <Link
                v-for="category in categories"
                :key="category.id"
                :href="category.url"
                class="group flex flex-col overflow-hidden rounded-3xl bg-white ring-1 ring-stone-100 transition hover:shadow-lift"
            >
                <div class="aspect-[4/3] overflow-hidden bg-brand-50">
                    <ResponsiveImage :image="category.image" :alt="category.name" sizes="(min-width: 1280px) 16vw, (min-width: 640px) 30vw, 48vw" img-class="size-full object-cover transition duration-500 group-hover:scale-105" />
                </div>
                <div class="flex flex-1 items-center justify-between gap-2 p-3.5">
                    <span class="line-clamp-2 text-sm font-semibold text-cocoa">{{ category.name }}</span>
                    <span class="shrink-0 text-xs text-stone-400 tabular-nums">{{ category.products_count }}</span>
                </div>
            </Link>
        </div>
    </section>

    <section v-if="featured.length" class="container-store mt-20">
        <SectionHeading :title="t('storefront.featured')" :link="route('storefront.shop')" :link-label="t('storefront.view_all')" />
        <ProductGrid :products="featured" class="mt-8" />
    </section>

    <section class="container-store mt-20">
        <SectionHeading :title="t('storefront.new_arrivals')" :link="route('storefront.shop')" :link-label="t('storefront.view_all')" />
        <ProductGrid :products="newArrivals" class="mt-8" />
    </section>
</template>
