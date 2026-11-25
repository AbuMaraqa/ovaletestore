<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import RowActions from '@/Components/DataTable/RowActions.vue';
import type { Column, Filter, FilterOption, TablePage, TableState } from '@/Components/DataTable/types';
import KtBadge from '@/Components/UI/KtBadge.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useConfirm } from '@/Composables/useConfirm';
import { useTrans } from '@/Composables/useTrans';

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
    brand: string | null;
    in_stock: boolean;
    stock: number | null;
    updated_at: string;
    created_at: string | null;
    edit_url: string | null;
    delete_url: string | null;
    view_url: string | null;
}

const props = defineProps<{
    products: TablePage<Row>;
    state: TableState;
    categories: { id: number; name: string; parent_id: number | null }[];
    brands: FilterOption[];
    statuses: string[];
}>();

const { t } = useTrans();
const { can } = useCan();
const { confirm } = useConfirm();

const columns = computed<Column[]>(() => [
    { key: 'name', label: t('admin.name'), fixed: true },
    { key: 'sku', label: t('admin.sku') },
    { key: 'price', label: t('admin.price') },
    { key: 'stock', label: t('admin.stock') },
    { key: 'categories', label: t('admin.categories') },
    { key: 'brand', label: t('admin.brand'), hidden: true },
    { key: 'status', label: t('common.status'), sortable: true },
    { key: 'created_at', label: t('admin.created'), sortable: true, hidden: true },
    { key: 'updated_at', label: t('admin.last_updated'), sortable: true, align: 'end' },
]);

const filters = computed<Filter[]>(() => [
    { key: 'status', label: t('common.status'), type: 'select', options: props.statuses.map((s) => ({ value: s, label: t(`admin.status_${s}`) })) },
    { key: 'category', label: t('admin.categories'), type: 'select', options: props.categories.map((c) => ({ value: c.id, label: c.parent_id ? `— ${c.name}` : c.name })) },
    { key: 'brand', label: t('admin.brand'), type: 'select', options: props.brands },
    { key: 'type', label: t('admin.type'), type: 'select', options: [{ value: 'simple', label: t('admin.type_simple') }, { value: 'variable', label: t('admin.type_variable') }] },
    { key: 'stock', label: t('admin.stock'), type: 'select', options: [{ value: 'in', label: t('admin.in_stock') }, { value: 'out', label: t('admin.out_of_stock') }] },
    { key: 'featured', label: t('admin.featured_filter'), type: 'select', options: [{ value: 'yes', label: t('admin.featured') }, { value: 'no', label: t('common.no') }] },
    { key: 'date', label: t('admin.created'), type: 'date_range' },
]);

const statusVariant = (s: string) => ({ published: 'success', draft: 'warning', archived: 'secondary' })[s] as 'success' | 'warning' | 'secondary';

async function bulk(action: 'publish' | 'draft' | 'archive' | 'delete', ids: number[], clear: () => void): Promise<void> {
    if (action === 'delete') {
        const ok = await confirm({ title: t('table.delete_count', { count: ids.length }), message: t('common.confirm_delete'), confirmLabel: t('common.delete') });
        if (!ok) return;
    }
    router.post(route('admin.products.bulk'), { action, ids }, { preserveScroll: true, onSuccess: clear });
}
</script>

<template>
    <PageHeader :title="t('admin.products')">
        <template #actions>
            <KtButton v-if="can('products.create')" :href="route('admin.products.create')" icon="plus">{{ t('admin.new_product') }}</KtButton>
        </template>
    </PageHeader>

    <DataTable
        :page="products"
        :state="state"
        :columns="columns"
        :filters="filters"
        :url="route('admin.products.index')"
        storage-key="products"
        :search-placeholder="t('admin.search_products')"
        :title="t('admin.products')"
        :selectable="can('products.update') || can('products.delete')"
    >
        <template #bulk="{ ids, clear }">
            <template v-if="can('products.update')">
                <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" @click="bulk('publish', ids as number[], clear)">{{ t('admin.status_published') }}</button>
                <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" @click="bulk('draft', ids as number[], clear)">{{ t('admin.status_draft') }}</button>
                <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" @click="bulk('archive', ids as number[], clear)">{{ t('admin.status_archived') }}</button>
            </template>
            <button v-if="can('products.delete')" type="button" class="kt-btn kt-btn-sm kt-btn-destructive" @click="bulk('delete', ids as number[], clear)">{{ t('table.delete_selected') }}</button>
        </template>

        <template #cell-name="{ row }">
            <div class="flex min-w-64 max-w-md items-center gap-3">
                <img v-if="row.thumb" :src="row.thumb" alt="" class="size-11 shrink-0 rounded-lg border border-border bg-white object-contain" loading="lazy" width="44" height="44" />
                <div v-else class="size-11 shrink-0 rounded-lg bg-muted" />
                <div class="min-w-0">
                    <component :is="row.edit_url ? Link : 'span'" :href="row.edit_url ?? undefined" class="line-clamp-2 font-medium text-mono hover:text-primary">{{ row.name }}</component>
                    <div v-if="row.type === 'variable' || row.is_featured" class="mt-1 flex gap-1.5">
                        <KtBadge v-if="row.type === 'variable'" variant="info">{{ t('admin.type_variable') }} · {{ row.variants_count }}</KtBadge>
                        <KtBadge v-if="row.is_featured" variant="primary">{{ t('admin.featured') }}</KtBadge>
                    </div>
                </div>
            </div>
        </template>
        <template #cell-sku="{ row }"><span class="font-mono text-xs text-secondary-foreground" dir="ltr">{{ row.sku ?? '—' }}</span></template>
        <template #cell-price="{ row }"><span class="whitespace-nowrap font-medium" dir="ltr">{{ row.price ?? '—' }}</span></template>
        <template #cell-stock="{ row }">
            <span :class="['kt-badge kt-badge-sm', row.in_stock ? 'kt-badge-success kt-badge-outline' : 'kt-badge-destructive kt-badge-outline']">
                {{ row.in_stock ? t('admin.in_stock') : t('admin.out_of_stock') }}<template v-if="row.stock !== null"> · {{ row.stock }}</template>
            </span>
        </template>
        <template #cell-categories="{ row }"><span class="block max-w-44 truncate text-sm text-secondary-foreground" :title="row.categories.join('، ')">{{ row.categories.join('، ') || '—' }}</span></template>
        <template #cell-brand="{ row }"><span class="text-sm">{{ row.brand ?? '—' }}</span></template>
        <template #cell-status="{ row }"><KtBadge :variant="statusVariant(row.status)">{{ t(`admin.status_${row.status}`) }}</KtBadge></template>
        <template #cell-created_at="{ row }"><span class="whitespace-nowrap text-sm text-muted-foreground">{{ row.created_at }}</span></template>
        <template #cell-updated_at="{ row }"><span class="whitespace-nowrap text-sm text-muted-foreground">{{ row.updated_at }}</span></template>
        <template #actions="{ row }">
            <RowActions :edit-url="row.edit_url" :delete-url="row.delete_url" :label="row.name" :activity="{ type: 'product', id: row.id }">
                <a v-if="row.view_url" :href="row.view_url" target="_blank" rel="noopener" class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" :title="t('admin.view_store')" :aria-label="t('admin.view_store')">
                    <i class="ki-filled ki-exit-right-corner" aria-hidden="true" />
                </a>
            </RowActions>
        </template>
    </DataTable>
</template>
