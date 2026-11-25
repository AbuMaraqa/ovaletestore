<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import RowActions from '@/Components/DataTable/RowActions.vue';
import type { Column, Filter, TablePage, TableState } from '@/Components/DataTable/types';
import KtBadge from '@/Components/UI/KtBadge.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

interface Row { id: number; name: string; is_active: boolean; is_featured: boolean; products_count: number; logo: string | null; edit_url: string | null; delete_url: string | null }

defineProps<{ brands: TablePage<Row>; state: TableState }>();
const { t } = useTrans();
const { can } = useCan();

const columns = computed<Column[]>(() => [
    { key: 'name', label: t('admin.name'), fixed: true },
    { key: 'products_count', label: t('admin.products'), sortable: true, align: 'center' },
    { key: 'is_active', label: t('common.status') },
    { key: 'sort_order', label: t('admin.sort_order'), sortable: true, hidden: true },
]);
const filters = computed<Filter[]>(() => [
    { key: 'status', label: t('common.status'), type: 'select', options: [{ value: 'active', label: t('admin.active') }, { value: 'inactive', label: t('admin.inactive') }] },
    { key: 'featured', label: t('admin.featured_filter'), type: 'select', options: [{ value: 'yes', label: t('admin.featured') }, { value: 'no', label: t('common.no') }] },
]);
</script>

<template>
    <PageHeader :title="t('admin.brands')">
        <template #actions><KtButton v-if="can('brands.create')" :href="route('admin.brands.create')" icon="plus">{{ t('admin.new_brand') }}</KtButton></template>
    </PageHeader>

    <DataTable :page="brands" :state="state" :columns="columns" :filters="filters" :url="route('admin.brands.index')" storage-key="brands" :search-placeholder="t('admin.search_brands')" :title="t('admin.brands')">
        <template #cell-name="{ row }">
            <div class="flex items-center gap-3">
                <img v-if="row.logo" :src="row.logo" alt="" class="size-10 rounded-lg border border-border bg-white object-contain p-0.5" loading="lazy" width="40" height="40" />
                <div v-else class="size-10 rounded-lg bg-muted" />
                <component :is="row.edit_url ? Link : 'span'" :href="row.edit_url ?? undefined" class="font-medium text-mono hover:text-primary">{{ row.name }}</component>
            </div>
        </template>
        <template #cell-products_count="{ row }"><span class="tabular-nums">{{ row.products_count }}</span></template>
        <template #cell-is_active="{ row }">
            <div class="flex flex-wrap gap-1">
                <KtBadge :variant="row.is_active ? 'success' : 'secondary'">{{ row.is_active ? t('admin.active') : t('admin.inactive') }}</KtBadge>
                <KtBadge v-if="row.is_featured" variant="primary">{{ t('admin.featured') }}</KtBadge>
            </div>
        </template>
        <template #actions="{ row }">
            <RowActions :edit-url="row.edit_url" :delete-url="row.delete_url" :label="row.name" :activity="{ type: 'brand', id: row.id }" />
        </template>
    </DataTable>
</template>
