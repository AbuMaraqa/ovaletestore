<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import KtBadge from '@/Components/UI/KtBadge.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtPagination from '@/Components/UI/KtPagination.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useDebounce } from '@/Composables/useDebounce';
import { useTrans } from '@/Composables/useTrans';
import type { PaginationLink, SharedProps } from '@/types';

interface Row {
    id: number;
    name: string;
    type: string;
    status: string;
    is_featured: boolean;
    sku: string | null;
    thumb: string | null;
    price: string | null;
    variants_count: number;
    categories: string[];
    updated_at: string;
    edit_url: string;
    view_url: string | null;
}

const props = defineProps<{
    products: { data: Row[]; meta: { links: PaginationLink[]; total: number; from: number | null; to: number | null } };
    filters: { filter?: { search?: string; status?: string; category?: string } };
    categories: { id: number; name: string }[];
    statuses: string[];
}>();

const { t } = useTrans();
const can = (permission: string) => usePage<SharedProps>().props.auth.permissions.includes(permission);

const search = ref(props.filters.filter?.search ?? '');
const status = ref(props.filters.filter?.status ?? '');
const category = ref(props.filters.filter?.category ?? '');

const reload = () =>
    router.get(
        route('admin.products.index'),
        { filter: { ...(search.value ? { search: search.value } : {}), ...(status.value ? { status: status.value } : {}), ...(category.value ? { category: category.value } : {}) } },
        { preserveState: true, replace: true },
    );
watch(search, useDebounce(reload, 350));
watch([status, category], reload);

const statusVariant = (s: string) => ({ published: 'success', draft: 'warning', archived: 'secondary' })[s] as 'success' | 'warning' | 'secondary';
</script>

<template>
    <PageHeader :title="t('admin.products')">
        <template #actions>
            <KtButton v-if="can('products.create')" :href="route('admin.products.create')" icon="plus">{{ t('admin.new_product') }}</KtButton>
        </template>
    </PageHeader>

    <KtCard flush>
        <template #toolbar>
            <div class="flex w-full flex-wrap gap-2">
                <input v-model="search" type="search" class="kt-input w-64" :placeholder="t('admin.search_products')" :aria-label="t('common.search')" />
                <select v-model="status" class="kt-select w-40" :aria-label="t('common.status')">
                    <option value="">{{ t('admin.filter_status') }}</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ t(`admin.status_${s}`) }}</option>
                </select>
                <select v-model="category" class="kt-select w-56" :aria-label="t('admin.categories')">
                    <option value="">{{ t('admin.filter_category') }}</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
        </template>

        <div class="kt-table-wrapper overflow-x-auto">
            <table class="kt-table">
                <thead>
                    <tr>
                        <th class="w-16" />
                        <th>{{ t('admin.name') }}</th>
                        <th>{{ t('admin.sku') }}</th>
                        <th>{{ t('admin.price') }}</th>
                        <th>{{ t('admin.categories') }}</th>
                        <th>{{ t('common.status') }}</th>
                        <th class="text-end">{{ t('admin.last_updated') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="product in products.data" :key="product.id">
                        <td>
                            <img v-if="product.thumb" :src="product.thumb" alt="" class="size-11 rounded-lg border border-border bg-white object-contain" loading="lazy" width="44" height="44" />
                            <div v-else class="size-11 rounded-lg bg-muted" />
                        </td>
                        <td class="max-w-sm">
                            <Link :href="product.edit_url" class="line-clamp-2 font-medium text-mono hover:text-primary">{{ product.name }}</Link>
                            <div class="mt-1 flex gap-1.5">
                                <KtBadge v-if="product.type === 'variable'" variant="info">{{ t('admin.type_variable') }} · {{ product.variants_count }}</KtBadge>
                                <KtBadge v-if="product.is_featured" variant="primary">{{ t('admin.featured') }}</KtBadge>
                            </div>
                        </td>
                        <td class="font-mono text-xs text-muted-foreground" dir="ltr">{{ product.sku ?? '—' }}</td>
                        <td class="whitespace-nowrap font-medium" dir="ltr">{{ product.price ?? '—' }}</td>
                        <td class="text-sm text-muted-foreground">{{ product.categories.join('، ') || '—' }}</td>
                        <td><KtBadge :variant="statusVariant(product.status)">{{ t(`admin.status_${product.status}`) }}</KtBadge></td>
                        <td class="whitespace-nowrap text-end text-sm text-muted-foreground">{{ product.updated_at }}</td>
                    </tr>
                    <tr v-if="!products.data.length">
                        <td colspan="7" class="py-12 text-center text-muted-foreground">{{ t('common.no_results') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <template #footer>
            <KtPagination :links="products.meta.links" :total="products.meta.total" :from="products.meta.from" :to="products.meta.to" class="w-full" />
        </template>
    </KtCard>
</template>
