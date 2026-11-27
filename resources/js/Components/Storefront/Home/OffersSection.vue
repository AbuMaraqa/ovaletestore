<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import ProductCard from '@/Components/Storefront/ProductCard.vue';
import ScrollRail from '@/Components/Storefront/ScrollRail.vue';
import { useGsap } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';
import type { ProductCard as ProductCardType } from '@/types';

defineProps<{ products: ProductCardType[] }>();
const { t } = useTrans();
const root = ref<HTMLElement | null>(null);

useGsap(root, ({ gsap }) => {
    gsap.from('[data-offer-banner]', {
        clipPath: 'inset(8% 8% 8% 8% round 1.5rem)',
        duration: 1.1,
        ease: 'power3.inOut',
        scrollTrigger: { trigger: '[data-offer-banner]', start: 'top 85%', once: true },
    });
});
</script>

<template>
    <section v-if="products.length" ref="root" class="container-store mt-24 lg:mt-28">
        <div data-offer-banner class="relative overflow-hidden rounded-3xl bg-sand px-5 py-12 sm:px-10 lg:px-14 lg:py-16">
            <div class="relative flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="text-sm font-semibold text-brand-700">{{ t('storefront.offers_eyebrow') }}</p>
                    <h2 class="mt-3 text-4xl font-extrabold text-cocoa sm:text-5xl">{{ t('storefront.offers_title') }}</h2>
                    <p class="mt-3 max-w-md text-stone-600">{{ t('storefront.offers_text') }}</p>
                </div>
                <Link :href="`${route('storefront.shop')}?sale=1`" class="btn-primary h-12 px-7">
                    {{ t('storefront.view_offers') }}
                    <Icon name="arrow" class="size-4 rtl:-scale-x-100" />
                </Link>
            </div>
            <ScrollRail :label="t('storefront.offers_title')" class="relative mt-10">
                <div v-for="product in products" :key="product.id" class="flex w-[46%] shrink-0 snap-start text-cocoa sm:w-[31%] md:w-[23.5%] lg:w-[18.9%]">
                    <ProductCard :product="product" />
                </div>
            </ScrollRail>
        </div>
    </section>
</template>
