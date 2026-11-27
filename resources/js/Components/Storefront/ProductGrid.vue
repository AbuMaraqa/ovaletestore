<script setup lang="ts">
import ProductCard from '@/Components/Storefront/ProductCard.vue';
import type { ProductCard as ProductCardType } from '@/types';

/**
 * Product grid: 5 cards per row on desktop (4 next to the catalog sidebar on xl, 5 on 2xl), 2–4 on smaller screens.
 * `wide` = 4 roomier cards per row (the homepage's 8 featured products fill two full rows).
 */
withDefaults(defineProps<{ products: ProductCardType[]; priorityCount?: number; reveal?: boolean; withSidebar?: boolean; wide?: boolean }>(), {
    priorityCount: 0,
    reveal: false,
    withSidebar: false,
    wide: false,
});
</script>

<template>
    <div
        :class="[
            'grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3',
            wide ? 'md:grid-cols-4 lg:gap-5' : withSidebar ? 'md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5' : 'md:grid-cols-4 lg:grid-cols-5',
        ]"
    >
        <ProductCard v-for="(product, i) in products" :key="product.id" :product="product" :priority="i < priorityCount" :data-reveal="reveal ? '' : undefined" />
    </div>
</template>
