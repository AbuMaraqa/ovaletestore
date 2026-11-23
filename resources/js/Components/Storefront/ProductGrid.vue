<script setup lang="ts">
import ProductCard from '@/Components/Storefront/ProductCard.vue';
import type { ProductCard as ProductCardType } from '@/types';

/**
 * Product grid: 5 cards per row on desktop (4 next to the catalog sidebar on xl, 5 on 2xl), 2–4 on smaller screens.
 */
withDefaults(defineProps<{ products: ProductCardType[]; priorityCount?: number; reveal?: boolean; withSidebar?: boolean }>(), {
    priorityCount: 0,
    reveal: false,
    withSidebar: false,
});
</script>

<template>
    <div
        :class="[
            'grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3',
            withSidebar ? 'md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5' : 'md:grid-cols-4 lg:grid-cols-5',
        ]"
    >
        <ProductCard v-for="(product, i) in products" :key="product.id" :product="product" :priority="i < priorityCount" :data-reveal="reveal ? '' : undefined" />
    </div>
</template>
