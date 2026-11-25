<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import RowActions from '@/Components/DataTable/RowActions.vue';
import type { Column, Filter, FilterOption, TablePage, TableState } from '@/Components/DataTable/types';
import KtBadge from '@/Components/UI/KtBadge.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

interface Row {
    id: number;
    name: string;
    parent: string | null;
    is_active: boolean;
    sort_order: number;
    products_count: number;
    image: string | null;
    edit_url: string | null;
    delete_url: string | null;
    view_url: string | null;
}

const props = defineProps<{ categories: TablePage<Row>; state: TableState; parents: FilterOption[] }>();
const { t } = useTrans();
const { can } = useCan();

const columns = computed<Column[]>(() => [
    { key: 'name', label: t('admin.name'), fixed: true },
    { key: 'parent', label: t('admin.parent_category') },
    { key: 'products_count', label: t('admin.products'), sortable: true, align: 'center' },
    { key: 'sort_order', label: t('admin.sort_order'), sortable: true, align: 'center' },
    { key: 'is_active', label: t('common.status') },
]);
const filters = computed<Filter[]>(() => [
    { key: 'level', label: t('admin.level'), type: 'select', options: [{ value: 'top', label: t('admin.top_level') }, { value: 'sub', label: t('admin.subcategory') }] },
    { key: 'parent', label: t('admin.parent_category'), type: 'select', options: props.parents },
    { key: 'status', label: t('common.status'), type: 'select', options: [{ value: 'active', label: t('admin.active') }, { value: 'inactive', label: t('admin.inactive') }] },
]);
</script>

<template>
    <PageHeader :title="t('admin.categories')">
        <template #actions><KtButton v-if="can('categories.create')" :href="route('admin.categories.create')" icon="plus">{{ t('admin.new_category') }}</KtButton></template>
    </PageHeader>

    <DataTable :page="categories" :state="state" :columns="columns" :filters="filters" :url="route('admin.categories.index')" storage-key="categories" :search-placeholder="t('admin.search_categories')" :title="t('admin.categories')">
        <template #cell-name="{ row }">
            <div class="flex items-center gap-3">
                <img v-if="row.image" :src="row.image" alt="" class="size-10 rounded-lg object-cover" loading="lazy" width="40" height="40" />
                <div v-else class="size-10 rounded-lg bg-muted" />
                <component :is="row.edit_url ? Link : 'span'" :href="row.edit_url ?? undefined" class="font-medium text-mono hover:text-primary">{{ row.name }}</component>
            </div>
        </template>
        <template #cell-parent="{ row }"><span class="text-sm text-secondary-foreground">{{ row.parent ?? t('admin.top_level') }}</span></template>
        <template #cell-products_count="{ row }"><span class="tabular-nums">{{ row.products_count }}</span></template>
        <template #cell-sort_order="{ row }"><span class="tabular-nums text-muted-foreground">{{ row.sort_order }}</span></template>
        <template #cell-is_active="{ row }"><KtBadge :variant="row.is_active ? 'success' : 'secondary'">{{ row.is_active ? t('admin.active') : t('admin.inactive') }}</KtBadge></template>
        <template #actions="{ row }">
            <RowActions :edit-url="row.edit_url" :delete-url="row.delete_url" :label="row.name" :activity="{ type: 'category', id: row.id }">
                <a v-if="row.view_url" :href="row.view_url" target="_blank" rel="noopener" class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" :title="t('admin.view_store')" :aria-label="t('admin.view_store')">
                    <i class="ki-filled ki-exit-right-corner" aria-hidden="true" />
                </a>
            </RowActions>
        </template>
    </DataTable>
</template>
