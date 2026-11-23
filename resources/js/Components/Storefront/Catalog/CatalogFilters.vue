<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useTrans } from '@/Composables/useTrans';
import type { CatalogFacets, CatalogFilterState } from '@/types/catalog';

/** Filter panel (sidebar on desktop, drawer body on mobile). Emits a partial filter change. */
const props = defineProps<{ facets: CatalogFacets; filters: CatalogFilterState; activeCategoryUrl: string | null }>();
const emit = defineEmits<{ change: [changes: Partial<CatalogFilterState>] }>();
const { t } = useTrans();

const categoryQuery = ref('');
const showAllBrands = ref(false);
const priceMin = ref(props.filters.price_min ?? '');
const priceMax = ref(props.filters.price_max ?? '');
watch(() => [props.filters.price_min, props.filters.price_max], ([a, b]) => {
    priceMin.value = a ?? '';
    priceMax.value = b ?? '';
});

const categories = computed(() =>
    props.facets.categories.filter((c) => c.name.toLowerCase().includes(categoryQuery.value.trim().toLowerCase())),
);
const brands = computed(() => (showAllBrands.value ? props.facets.brands : props.facets.brands.slice(0, 8)));

function toggleBrand(id: number): void {
    const next = props.filters.brands.includes(id) ? props.filters.brands.filter((b) => b !== id) : [...props.filters.brands, id];
    emit('change', { brands: next });
}

function applyPrice(): void {
    emit('change', { price_min: priceMin.value === '' ? null : String(priceMin.value), price_max: priceMax.value === '' ? null : String(priceMax.value) });
}
</script>

<template>
    <div class="space-y-7 text-sm">
        <!-- Categories -->
        <section>
            <h3 class="mb-3 text-sm font-bold text-cocoa">{{ t('storefront.categories') }}</h3>
            <input v-model="categoryQuery" type="search" :placeholder="t('common.search')" :aria-label="t('common.search')" class="mb-2 h-11 w-full rounded-lg border-0 bg-white px-3 text-sm ring-1 ring-stone-200 focus:ring-2 focus:ring-brand-500" />
            <ul class="max-h-80 space-y-0.5 overflow-y-auto pe-1" data-lenis-prevent>
                <li>
                    <Link
                        :href="route('storefront.shop')"
                        :class="['flex items-center justify-between rounded-lg px-2.5 py-2 transition', !activeCategoryUrl ? 'bg-cocoa font-semibold text-white' : 'text-stone-700 hover:bg-stone-100']"
                    >
                        {{ t('storefront.all_categories') }}
                    </Link>
                </li>
                <li v-for="c in categories" :key="c.id">
                    <Link
                        :href="c.url"
                        :class="['flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 transition', activeCategoryUrl === c.url ? 'bg-cocoa font-semibold text-white' : 'text-stone-700 hover:bg-stone-100']"
                    >
                        <span class="truncate">{{ c.name }}</span>
                        <span :class="['shrink-0 text-xs tabular-nums', activeCategoryUrl === c.url ? 'text-white/70' : 'text-stone-500']">{{ c.count }}</span>
                    </Link>
                </li>
            </ul>
        </section>

        <!-- Brands -->
        <section v-if="facets.brands.length">
            <h3 class="mb-3 text-sm font-bold text-cocoa">{{ t('storefront.brands') }}</h3>
            <ul class="space-y-1">
                <li v-for="b in brands" :key="b.id">
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1.5 py-1.5 hover:bg-stone-100">
                        <input type="checkbox" class="size-5 rounded border-stone-300 text-brand-600 focus:ring-brand-500" :checked="filters.brands.includes(b.id)" @change="toggleBrand(b.id)" />
                        <span class="flex-1 truncate text-stone-700">{{ b.name }}</span>
                        <span class="text-xs text-stone-500 tabular-nums">{{ b.count }}</span>
                    </label>
                </li>
            </ul>
            <button v-if="facets.brands.length > 8" type="button" class="mt-1 px-1.5 text-xs font-semibold text-brand-700 hover:underline" @click="showAllBrands = !showAllBrands">
                {{ showAllBrands ? t('storefront.show_less') : t('storefront.show_more') }}
            </button>
        </section>

        <!-- Price -->
        <section>
            <h3 class="mb-3 text-sm font-bold text-cocoa">{{ t('storefront.price') }} (₪)</h3>
            <form class="flex items-center gap-2" @submit.prevent="applyPrice">
                <input v-model="priceMin" type="number" min="0" inputmode="decimal" :placeholder="t('storefront.min')" :aria-label="t('storefront.min')" class="h-11 w-full rounded-lg border-0 bg-white px-2.5 text-sm ring-1 ring-stone-200 focus:ring-2 focus:ring-brand-500" dir="ltr" />
                <span class="text-stone-400">–</span>
                <input v-model="priceMax" type="number" min="0" inputmode="decimal" :placeholder="t('storefront.max')" :aria-label="t('storefront.max')" class="h-11 w-full rounded-lg border-0 bg-white px-2.5 text-sm ring-1 ring-stone-200 focus:ring-2 focus:ring-brand-500" dir="ltr" />
                <button type="submit" class="h-11 shrink-0 rounded-lg bg-cocoa px-3 text-xs font-semibold text-white hover:bg-brand-800">{{ t('storefront.apply') }}</button>
            </form>
        </section>

        <!-- Availability / offers -->
        <section class="space-y-1">
            <h3 class="mb-3 text-sm font-bold text-cocoa">{{ t('storefront.availability') }}</h3>
            <label class="flex cursor-pointer items-center justify-between rounded-lg px-1.5 py-1.5 hover:bg-stone-100">
                <span class="text-stone-700">{{ t('storefront.in_stock_only') }}</span>
                <input type="checkbox" class="size-5 rounded border-stone-300 text-brand-600 focus:ring-brand-500" :checked="filters.in_stock" @change="emit('change', { in_stock: !filters.in_stock })" />
            </label>
            <label class="flex cursor-pointer items-center justify-between rounded-lg px-1.5 py-1.5 hover:bg-stone-100">
                <span class="text-stone-700">{{ t('storefront.on_sale_only') }}</span>
                <input type="checkbox" class="size-5 rounded border-stone-300 text-brand-600 focus:ring-brand-500" :checked="filters.sale" @change="emit('change', { sale: !filters.sale })" />
            </label>
        </section>
    </div>
</template>
