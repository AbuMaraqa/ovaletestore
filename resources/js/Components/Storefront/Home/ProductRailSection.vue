<script setup lang="ts">
import ProductCard from '@/Components/Storefront/ProductCard.vue';
import ScrollRail from '@/Components/Storefront/ScrollRail.vue';
import SectionHeading from '@/Components/Storefront/SectionHeading.vue';
import type { ProductCard as ProductCardType } from '@/types';

defineProps<{ title: string; products: ProductCardType[]; link?: string; linkLabel?: string }>();
</script>

<template>
    <section v-if="products.length" class="container-store mt-24 lg:mt-28">
        <SectionHeading data-reveal :title="title" :link="link" :link-label="linkLabel" />
        <ScrollRail :label="title" class="mt-8">
            <div v-for="product in products" :key="product.id" data-reveal class="flex w-[46%] shrink-0 snap-start sm:w-[31%] md:w-[23.5%] lg:w-[18.9%]">
                <ProductCard :product="product" />
            </div>
        </ScrollRail>
    </section>
</template>
