<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Pagination from '@/Components/Storefront/Pagination.vue';
import ProductGrid from '@/Components/Storefront/ProductGrid.vue';
import { useTrans } from '@/Composables/useTrans';
import type { CategoryCard, Paginated, PaginationLink, ProductCard } from '@/types';

const props = defineProps<{
    products: Paginated<ProductCard> & { meta: { total: number; links: PaginationLink[] } };
    category: { name: string; description: string | null; url: string } | null;
    categories: CategoryCard[];
    filters: { q: string | null; sort: string; in_stock: boolean; sale: boolean };
    sorts: string[];
}>();

const { t } = useTrans();
const baseUrl = computed(() => props.category?.url ?? route('storefront.shop'));
const title = computed(() => props.category?.name ?? (props.filters.q ? t('storefront.results_for', { q: props.filters.q }) : t('storefront.shop')));

function apply(changes: Partial<{ sort: string; in_stock: boolean; sale: boolean }>): void {
    const next = { ...props.filters, ...changes };
    router.get(
        baseUrl.value,
        {
            ...(next.q ? { q: next.q } : {}),
            ...(next.sort !== 'newest' ? { sort: next.sort } : {}),
            ...(next.in_stock ? { in_stock: 1 } : {}),
            ...(next.sale ? { sale: 1 } : {}),
        },
        { preserveState: true, preserveScroll: false },
    );
}
</script>

<template>
    <div class="container-store py-10 lg:py-14">
        <header class="max-w-3xl">
            <nav class="text-sm text-stone-500" aria-label="Breadcrumb">
                <Link :href="route('storefront.home')" class="hover:text-cocoa">{{ t('common.home') }}</Link>
                <span class="mx-2">/</span>
                <span class="text-cocoa">{{ title }}</span>
            </nav>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-cocoa sm:text-4xl">{{ title }}</h1>
            <!-- Category descriptions are sanitized server-side (RichText). -->
            <div v-if="category?.description" class="rich-text mt-3" v-html="category.description" />
        </header>

        <!-- Category chips (horizontal scroll on mobile) -->
        <div class="-mx-4 mt-8 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:px-0">
            <Link :href="route('storefront.shop')" :class="['chip shrink-0', !category ? 'bg-cocoa text-white ring-cocoa' : '']">{{ t('storefront.all_categories') }}</Link>
            <Link
                v-for="c in categories"
                :key="c.id"
                :href="c.url"
                :class="['chip shrink-0', category?.url === c.url ? 'bg-cocoa text-white ring-cocoa' : '']"
            >
                {{ c.name }}
            </Link>
        </div>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 pb-5">
            <p class="text-sm text-stone-500">{{ t('storefront.products_count', { count: products.meta.total }) }}</p>
            <div class="flex flex-wrap items-center gap-3">
                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm">
                    <input type="checkbox" class="size-4 rounded border-stone-300 text-brand-600 focus:ring-brand-500" :checked="filters.sale" @change="apply({ sale: ($event.target as HTMLInputElement).checked })" />
                    {{ t('storefront.on_sale_only') }}
                </label>
                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm">
                    <input type="checkbox" class="size-4 rounded border-stone-300 text-brand-600 focus:ring-brand-500" :checked="filters.in_stock" @change="apply({ in_stock: ($event.target as HTMLInputElement).checked })" />
                    {{ t('storefront.in_stock_only') }}
                </label>
                <label class="sr-only" for="sort">{{ t('storefront.sort') }}</label>
                <select
                    id="sort"
                    class="h-11 rounded-full border-0 bg-white ps-4 pe-10 text-sm ring-1 ring-stone-200 focus:ring-2 focus:ring-brand-500"
                    :value="filters.sort"
                    @change="apply({ sort: ($event.target as HTMLSelectElement).value })"
                >
                    <option v-for="sort in sorts" :key="sort" :value="sort">{{ t(`storefront.sort_${sort}`) }}</option>
                </select>
            </div>
        </div>

        <ProductGrid v-if="products.data.length" :products="products.data" :priority-count="4" class="mt-8" />
        <p v-else class="py-24 text-center text-stone-500">{{ t('storefront.empty_category') }}</p>

        <Pagination :links="products.meta.links" class="mt-12" />
    </div>
</template>
