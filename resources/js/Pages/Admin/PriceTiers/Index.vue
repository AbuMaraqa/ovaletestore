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
    id: number; code: string; name: string; discount_percent: number; is_active: boolean;
    traders_count: number; prices_count: number; edit_url: string | null; delete_url: string | null;
}

defineProps<{ tiers: TablePage<Row>; state: TableState }>();
const { t } = useTrans();
const { can } = useCan();

const columns = computed<Column[]>(() => [
    { key: 'name', label: t('admin.name'), fixed: true },
    { key: 'discount_percent', label: t('traders.automatic_discount'), sortable: true, align: 'center' },
    { key: 'prices_count', label: t('traders.exact_prices'), sortable: true, align: 'center' },
    { key: 'traders_count', label: t('traders.list'), sortable: true, align: 'center' },
    { key: 'is_active', label: t('common.status') },
]);
const filters = computed<Filter[]>(() => [
    { key: 'status', label: t('common.status'), type: 'select', options: [{ value: 'active', label: t('admin.active') }, { value: 'inactive', label: t('admin.inactive') }] },
]);
</script>

<template>
    <PageHeader :title="t('traders.tiers')" :description="t('traders.tiers_help')">
        <template #actions>
            <KtButton v-if="can('price_tiers.create')" :href="route('admin.price-tiers.create')" icon="plus">{{ t('traders.new_tier') }}</KtButton>
        </template>
    </PageHeader>

    <DataTable :page="tiers" :state="state" :columns="columns" :filters="filters" :url="route('admin.price-tiers.index')" storage-key="price-tiers">
        <template #cell-name="{ row }">
            <component :is="row.edit_url ? Link : 'span'" :href="row.edit_url ?? undefined" class="font-medium text-mono hover:text-primary">{{ row.name }}</component>
            <span class="block font-mono text-xs text-secondary-foreground" dir="ltr">{{ row.code }}</span>
        </template>
        <template #cell-discount_percent="{ row }"><span class="tabular-nums">{{ row.discount_percent ? `${row.discount_percent}%` : '—' }}</span></template>
        <template #cell-prices_count="{ row }"><span class="tabular-nums">{{ row.prices_count }}</span></template>
        <template #cell-traders_count="{ row }"><span class="tabular-nums">{{ row.traders_count }}</span></template>
        <template #cell-is_active="{ row }"><KtBadge :variant="row.is_active ? 'success' : 'secondary'">{{ row.is_active ? t('admin.active') : t('admin.inactive') }}</KtBadge></template>
        <template #actions="{ row }">
            <RowActions :edit-url="row.edit_url" :delete-url="row.delete_url" :label="row.name" :activity="{ type: 'price_tier', id: row.id }" />
        </template>
    </DataTable>
</template>
