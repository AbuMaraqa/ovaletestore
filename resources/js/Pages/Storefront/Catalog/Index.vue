<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CatalogFilters from '@/Components/Storefront/Catalog/CatalogFilters.vue';
import Icon from '@/Components/Storefront/Icon.vue';
import Pagination from '@/Components/Storefront/Pagination.vue';
import ProductGrid from '@/Components/Storefront/ProductGrid.vue';
import { lockScroll } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';
import type { Paginated, PaginationLink, ProductCard } from '@/types';
import { filtersToQuery, type CatalogFacets, type CatalogFilterState } from '@/types/catalog';

const props = defineProps<{
    products: Paginated<ProductCard> & { meta: { total: number; from: number | null; to: number | null; links: PaginationLink[] } };
    category: { name: string; description: string | null; url: string } | null;
    brand: { name: string; description: string | null; url: string; logo: string | null } | null;
    facets: CatalogFacets;
    filters: CatalogFilterState;
    sorts: string[];
}>();

const { t } = useTrans();
const drawer = ref(false);
watch(drawer, (open) => lockScroll(open));

const baseUrl = computed(() => props.brand?.url ?? props.category?.url ?? route('storefront.shop'));
const title = computed(() => props.brand?.name ?? props.category?.name ?? (props.filters.q ? t('storefront.results_for', { q: props.filters.q }) : t('storefront.shop')));

function apply(changes: Partial<CatalogFilterState>): void {
    router.get(baseUrl.value, filtersToQuery({ ...props.filters, ...changes }), { preserveState: true, preserveScroll: true });
}

/** Removable chips for every active filter. */
const chips = computed(() => {
    const f = props.filters;
    const list: { key: string; label: string; clear: Partial<CatalogFilterState> }[] = [];
    if (f.q) list.push({ key: 'q', label: `“${f.q}”`, clear: { q: null } });
    f.brands.forEach((id) => {
        const brand = props.facets.brands.find((b) => b.id === id);
        if (brand) list.push({ key: `b${id}`, label: brand.name, clear: { brands: f.brands.filter((b) => b !== id) } });
    });
    if (f.price_min || f.price_max) list.push({ key: 'price', label: `${f.price_min ?? 0} – ${f.price_max ?? '∞'} ₪`, clear: { price_min: null, price_max: null } });
    if (f.in_stock) list.push({ key: 'stock', label: t('storefront.in_stock_only'), clear: { in_stock: false } });
    if (f.sale) list.push({ key: 'sale', label: t('storefront.on_sale_only'), clear: { sale: false } });
    return list;
});

const clearAll = () => apply({ q: null, brands: [], price_min: null, price_max: null, in_stock: false, sale: false });
</script>

<template>
    <div>
        <!-- Page banner -->
        <div class="border-b border-stone-200/70 bg-gradient-to-b from-brand-50 to-cream">
            <div class="container-store py-8 lg:py-10">
                <nav class="text-sm text-stone-500" aria-label="Breadcrumb">
                    <Link :href="route('storefront.home')" class="hover:text-cocoa">{{ t('common.home') }}</Link>
                    <span class="mx-2">/</span>
                    <template v-if="brand"><Link :href="route('storefront.brands')" class="hover:text-cocoa">{{ t('storefront.brands') }}</Link><span class="mx-2">/</span></template>
                    <span class="text-cocoa">{{ title }}</span>
                </nav>
                <header class="mt-3 flex items-center gap-4">
                    <img v-if="brand?.logo" :src="brand.logo" :alt="brand.name" class="size-16 rounded-2xl bg-white object-contain p-1.5 ring-1 ring-stone-200" />
                    <h1 class="text-2xl font-extrabold tracking-tight text-cocoa sm:text-4xl">{{ title }}</h1>
                </header>
            </div>
        </div>

        <div class="container-store py-8">
            <div class="grid gap-8 lg:grid-cols-[15.5rem_1fr]">
                <!-- Sidebar (desktop) -->
                <aside class="hidden lg:block">
                    <div class="sticky top-36">
                        <CatalogFilters :facets="facets" :filters="filters" :active-category-url="category?.url ?? null" @change="apply" />
                    </div>
                </aside>

                <div class="min-w-0">
                    <!-- Toolbar -->
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-stone-500">
                            {{ t('storefront.showing_results', { from: products.meta.from ?? 0, to: products.meta.to ?? 0, total: products.meta.total }) }}
                        </p>
                        <div class="flex items-center gap-2">
                            <button type="button" class="chip h-10 gap-2 lg:hidden" @click="drawer = true">
                                <Icon name="menu" class="size-4" /> {{ t('storefront.filters') }}
                                <span v-if="chips.length" class="rounded-full bg-brand-600 px-1.5 text-[11px] font-bold text-white">{{ chips.length }}</span>
                            </button>
                            <label class="sr-only" for="sort">{{ t('storefront.sort') }}</label>
                            <select
                                id="sort"
                                class="h-10 rounded-full border-0 bg-white ps-5 pe-10 text-sm font-medium text-cocoa ring-1 ring-stone-200 focus:ring-2 focus:ring-brand-500"
                                :value="filters.sort"
                                @change="apply({ sort: ($event.target as HTMLSelectElement).value })"
                            >
                                <option v-for="sort in sorts" :key="sort" :value="sort">{{ t(`storefront.sort_${sort}`) }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="chips.length" class="mt-4 flex flex-wrap gap-1.5">
                        <button
                            v-for="chip in chips"
                            :key="chip.key"
                            type="button"
                            class="inline-flex h-8 items-center gap-1.5 rounded-full bg-brand-50 px-3 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 hover:bg-brand-100"
                            @click="apply(chip.clear)"
                        >
                            {{ chip.label }} <Icon name="close" class="size-3.5" />
                        </button>
                        <button v-if="chips.length > 1" type="button" class="h-8 px-2 text-xs font-semibold text-stone-500 hover:text-cocoa hover:underline" @click="clearAll">
                            {{ t('storefront.clear_filters') }}
                        </button>
                    </div>

                    <ProductGrid v-if="products.data.length" :products="products.data" :priority-count="6" with-sidebar class="mt-6" />
                    <div v-else class="py-24 text-center">
                        <p class="text-stone-500">{{ t('storefront.empty_category') }}</p>
                        <button v-if="chips.length" type="button" class="btn-ghost mt-6" @click="clearAll">{{ t('storefront.clear_filters') }}</button>
                    </div>

                    <Pagination :links="products.meta.links" class="mt-12" />
                </div>
            </div>
        </div>

        <!-- Filters drawer (mobile) -->
        <Teleport to="body">
            <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150" leave-to-class="opacity-0">
                <div v-if="drawer" class="fixed inset-0 z-50 bg-stone-900/40 backdrop-blur-sm lg:hidden" @click="drawer = false" />
            </Transition>
            <Transition enter-active-class="transition-transform duration-300" enter-from-class="translate-y-full" leave-active-class="transition-transform duration-200" leave-to-class="translate-y-full">
                <div v-if="drawer" class="fixed inset-x-0 bottom-0 z-50 flex max-h-[85vh] flex-col rounded-t-3xl bg-cream shadow-2xl lg:hidden" role="dialog" aria-modal="true" :aria-label="t('storefront.filters')">
                    <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4">
                        <span class="font-bold text-cocoa">{{ t('storefront.filters') }}</span>
                        <button type="button" class="inline-flex size-10 items-center justify-center rounded-full hover:bg-stone-100" :aria-label="t('common.close')" @click="drawer = false"><Icon name="close" class="size-5" /></button>
                    </div>
                    <div class="overflow-y-auto px-5 py-5" data-lenis-prevent>
                        <CatalogFilters :facets="facets" :filters="filters" :active-category-url="category?.url ?? null" @change="apply" />
                    </div>
                    <div class="border-t border-stone-200 p-4">
                        <button type="button" class="btn-primary h-12 w-full" @click="drawer = false">{{ t('storefront.products_count', { count: products.meta.total }) }}</button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
