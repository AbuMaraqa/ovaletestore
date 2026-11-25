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

interface Row {
    id: number; name: string; type: string; cost: string | null; free_over: string | null; min_order: string | null;
    is_active: boolean; orders_count: number; edit_url: string | null; delete_url: string | null;
}

const props = defineProps<{ methods: TablePage<Row>; state: TableState; types: string[] }>();
const { t } = useTrans();
const { can } = useCan();

const columns = computed<Column[]>(() => [
    { key: 'name', label: t('admin.name'), fixed: true },
    { key: 'type', label: t('admin.shipping_type') },
    { key: 'cost', label: t('admin.shipping_cost'), sortable: true },
    { key: 'free_over', label: t('admin.free_over') },
    { key: 'min_order', label: t('admin.min_order_for_method'), hidden: true },
    { key: 'orders_count', label: t('admin.orders'), sortable: true, align: 'center' },
    { key: 'is_active', label: t('common.status') },
]);
const filters = computed<Filter[]>(() => [
    { key: 'type', label: t('admin.shipping_type'), type: 'select', options: props.types.map((type) => ({ value: type, label: t(`admin.shipping_type_${type}`) })) },
    { key: 'status', label: t('common.status'), type: 'select', options: [{ value: 'active', label: t('admin.active') }, { value: 'inactive', label: t('admin.inactive') }] },
]);
</script>

<template>
    <PageHeader :title="t('admin.shipping_methods')" :description="t('admin.shipping_methods_help')">
        <template #actions><KtButton v-if="can('shipping.create')" :href="route('admin.shipping-methods.create')" icon="plus">{{ t('admin.new_shipping_method') }}</KtButton></template>
    </PageHeader>

    <DataTable :page="methods" :state="state" :columns="columns" :filters="filters" :url="route('admin.shipping-methods.index')" storage-key="shipping" :search-placeholder="t('admin.search_shipping')" :title="t('admin.shipping_methods')">
        <template #cell-name="{ row }">
            <component :is="row.edit_url ? Link : 'span'" :href="row.edit_url ?? undefined" class="font-medium text-mono hover:text-primary">{{ row.name }}</component>
        </template>
        <template #cell-type="{ row }">{{ t(`admin.shipping_type_${row.type}`) }}</template>
        <template #cell-cost="{ row }"><span class="tabular-nums" dir="ltr">{{ row.cost ?? t('cart.free') }}</span></template>
        <template #cell-free_over="{ row }"><span class="tabular-nums" dir="ltr">{{ row.free_over ?? '—' }}</span></template>
        <template #cell-min_order="{ row }"><span class="tabular-nums" dir="ltr">{{ row.min_order ?? '—' }}</span></template>
        <template #cell-orders_count="{ row }"><span class="tabular-nums">{{ row.orders_count }}</span></template>
        <template #cell-is_active="{ row }"><KtBadge :variant="row.is_active ? 'success' : 'secondary'">{{ row.is_active ? t('admin.active') : t('admin.inactive') }}</KtBadge></template>
        <template #actions="{ row }">
            <RowActions :edit-url="row.edit_url" :delete-url="row.delete_url" :label="row.name" :activity="{ type: 'shipping_method', id: row.id }" />
        </template>
    </DataTable>
</template>
