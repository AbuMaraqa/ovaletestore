<script setup lang="ts">
import ProductCard from '@/Components/Storefront/ProductCard.vue';
import type { ProductCard as ProductCardType } from '@/types';

withDefaults(defineProps<{ products: ProductCardType[]; priorityCount?: number; reveal?: boolean }>(), { priorityCount: 0, reveal: false });
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 xl:grid-cols-4">
        <ProductCard v-for="(product, i) in products" :key="product.id" :product="product" :priority="i < priorityCount" :data-reveal="reveal ? '' : undefined" />
    </div>
</template>
