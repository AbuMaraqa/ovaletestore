<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import RowActions from '@/Components/DataTable/RowActions.vue';
import type { Column, Filter, TablePage, TableState } from '@/Components/DataTable/types';
import KtIcon from '@/Components/UI/KtIcon.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';
import type { Avatar } from '@/types';

interface Row {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    avatar: Avatar;
    is_active: boolean;
    from_woocommerce: boolean;
    orders_count: number;
    spent: string;
    last_order: string | null;
    registered: string | null;
    show_url: string;
    edit_url: string | null;
    delete_url: string | null;
}

defineProps<{ customers: TablePage<Row>; state: TableState; stats: { total: number; with_orders: number; new_this_month: number } }>();
const { t } = useTrans();
const { can } = useCan();

const columns = computed<Column[]>(() => [
    { key: 'name', label: t('customers.customer'), sortable: true, fixed: true },
    { key: 'phone', label: t('admin.phone') },
    { key: 'orders_count', label: t('customers.orders'), sortable: true, align: 'center' },
    { key: 'spent', label: t('customers.spent'), sortable: true, align: 'end' },
    { key: 'last_order_at', label: t('customers.last_order'), sortable: true },
    { key: 'source', label: t('customers.source'), hidden: true },
    { key: 'is_active', label: t('common.status') },
    { key: 'created_at', label: t('customers.registered'), sortable: true, hidden: true },
]);
const filters = computed<Filter[]>(() => [
    { key: 'orders', label: t('customers.has_orders'), type: 'select', options: [{ value: 'with', label: t('customers.with_orders') }, { value: 'without', label: t('customers.without_orders') }] },
    { key: 'status', label: t('common.status'), type: 'select', options: [{ value: 'active', label: t('admin.active') }, { value: 'inactive', label: t('admin.inactive') }] },
    { key: 'source', label: t('customers.source'), type: 'select', options: [{ value: 'woocommerce', label: t('customers.from_woocommerce') }, { value: 'new', label: t('customers.registered_here') }] },
    { key: 'date', label: t('customers.registered'), type: 'date_range' },
]);
const cards = computed(() => [
    { key: 'total', icon: 'profile-circle', tone: 'bg-primary/10 text-primary' },
    { key: 'with_orders', icon: 'handcart', tone: 'bg-green-500/10 text-green-600' },
    { key: 'new_this_month', icon: 'calendar-tick', tone: 'bg-sky-500/10 text-sky-600' },
] as const);
</script>

<template>
    <PageHeader :title="t('customers.title')" :description="t('customers.subtitle')">
        <template #actions>
            <KtButton v-if="can('customers.create')" :href="route('admin.customers.create')" icon="plus">{{ t('customers.new') }}</KtButton>
        </template>
    </PageHeader>

    <div class="mb-6 grid gap-5 sm:grid-cols-3">
        <div v-for="card in cards" :key="card.key" class="kt-card flex-row items-center gap-4 p-5">
            <span :class="['flex size-12 items-center justify-center rounded-xl', card.tone]"><KtIcon :name="card.icon" class="text-2xl" /></span>
            <div>
                <p class="text-2xl font-semibold tabular-nums text-mono">{{ stats[card.key] }}</p>
                <p class="text-sm text-secondary-foreground">{{ t(card.key === 'total' ? 'customers.total' : `customers.${card.key}`) }}</p>
            </div>
        </div>
    </div>

    <DataTable :page="customers" :state="state" :columns="columns" :filters="filters" :url="route('admin.customers.index')" storage-key="customers" :search-placeholder="t('customers.search')" :title="t('customers.title')">
        <template #cell-name="{ row }">
            <div class="flex items-center gap-3">
                <UserAvatar :avatar="row.avatar" :name="row.name" :ring="false" />
                <div class="min-w-0">
                    <Link :href="row.show_url" class="font-medium text-mono hover:text-primary">{{ row.name }}</Link>
                    <p class="truncate text-xs text-muted-foreground" dir="ltr">{{ row.email }}</p>
                </div>
            </div>
        </template>
        <template #cell-phone="{ row }"><span class="text-sm" dir="ltr">{{ row.phone ?? '—' }}</span></template>
        <template #cell-orders_count="{ row }">
            <span :class="['kt-badge kt-badge-sm tabular-nums', row.orders_count ? 'kt-badge-primary kt-badge-outline' : 'kt-badge-outline']">{{ row.orders_count }}</span>
        </template>
        <template #cell-spent="{ row }"><span class="whitespace-nowrap font-medium tabular-nums" dir="ltr">{{ row.spent }}</span></template>
        <template #cell-last_order_at="{ row }"><span class="text-sm text-secondary-foreground">{{ row.last_order ?? '—' }}</span></template>
        <template #cell-source="{ row }">
            <span class="kt-badge kt-badge-sm kt-badge-outline">{{ row.from_woocommerce ? t('customers.from_woocommerce') : t('customers.registered_here') }}</span>
        </template>
        <template #cell-is_active="{ row }">
            <span :class="['kt-badge kt-badge-sm', row.is_active ? 'kt-badge-success kt-badge-outline' : 'kt-badge-outline']">{{ row.is_active ? t('admin.active') : t('admin.inactive') }}</span>
        </template>
        <template #cell-created_at="{ row }"><span class="text-sm text-secondary-foreground">{{ row.registered }}</span></template>
        <template #actions="{ row }">
            <RowActions :view-url="row.show_url" :edit-url="row.edit_url" :delete-url="row.delete_url" :label="row.name" :activity="{ type: 'user', id: row.id }" />
        </template>
    </DataTable>
</template>
