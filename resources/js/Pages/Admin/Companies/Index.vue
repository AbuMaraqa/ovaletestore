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
    id: number; name: string; contact_name: string | null; phone: string | null; city: string | null; is_active: boolean;
    orders_count: number; balance: string; has_debt: boolean; show_url: string; edit_url: string | null; delete_url: string | null;
}

const props = defineProps<{ companies: TablePage<Row>; state: TableState; outstanding: string; cities: FilterOption[] }>();
const { t } = useTrans();
const { can } = useCan();

const columns = computed<Column[]>(() => [
    { key: 'name', label: t('admin.company'), sortable: true, fixed: true },
    { key: 'contact_name', label: t('admin.contact_name') },
    { key: 'phone', label: t('admin.phone') },
    { key: 'orders_count', label: t('admin.orders'), sortable: true, align: 'center' },
    { key: 'balance', label: t('admin.balance'), align: 'end' },
    { key: 'is_active', label: t('common.status') },
]);
const filters = computed<Filter[]>(() => [
    { key: 'status', label: t('common.status'), type: 'select', options: [{ value: 'active', label: t('admin.active') }, { value: 'inactive', label: t('admin.inactive') }] },
    { key: 'city', label: t('admin.city'), type: 'select', options: props.cities },
]);
</script>

<template>
    <PageHeader :title="t('admin.companies')" :description="`${t('admin.outstanding_total')}: ${outstanding}`">
        <template #actions><KtButton v-if="can('companies.create')" :href="route('admin.companies.create')" icon="plus">{{ t('admin.new_company') }}</KtButton></template>
    </PageHeader>

    <DataTable :page="companies" :state="state" :columns="columns" :filters="filters" :url="route('admin.companies.index')" storage-key="companies" :search-placeholder="t('admin.search_companies')" :title="t('admin.companies')">
        <template #cell-name="{ row }">
            <Link :href="row.show_url" class="font-medium text-mono hover:text-primary">{{ row.name }}</Link>
            <span v-if="row.city" class="block text-xs text-muted-foreground">{{ row.city }}</span>
        </template>
        <template #cell-contact_name="{ row }"><span class="text-sm">{{ row.contact_name ?? '—' }}</span></template>
        <template #cell-phone="{ row }"><span class="text-sm" dir="ltr">{{ row.phone ?? '—' }}</span></template>
        <template #cell-orders_count="{ row }"><span class="tabular-nums">{{ row.orders_count }}</span></template>
        <template #cell-balance="{ row }">
            <span :class="['font-semibold tabular-nums', row.has_debt ? 'text-destructive' : 'text-muted-foreground']" dir="ltr">{{ row.balance }}</span>
        </template>
        <template #cell-is_active="{ row }"><KtBadge :variant="row.is_active ? 'success' : 'secondary'">{{ row.is_active ? t('admin.active') : t('admin.inactive') }}</KtBadge></template>
        <template #actions="{ row }">
            <RowActions :view-url="row.show_url" :edit-url="row.edit_url" :delete-url="row.delete_url" :label="row.name" :activity="{ type: 'company', id: row.id }" />
        </template>
    </DataTable>
</template>
