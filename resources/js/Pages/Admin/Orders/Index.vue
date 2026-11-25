<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import OrderStatusBadge from '@/Components/Admin/OrderStatusBadge.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import RowActions from '@/Components/DataTable/RowActions.vue';
import type { Column, Filter, FilterOption, TablePage, TableState } from '@/Components/DataTable/types';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

interface Row {
    id: number;
    number: string;
    customer: string;
    customer_url: string | null;
    phone: string;
    email: string | null;
    city: string | null;
    shipping: string | null;
    payment: string;
    status: string;
    channel: 'web' | 'pos';
    items_count: number;
    total: string;
    date: string;
    ago: string;
    show_url: string;
    delete_url: string | null;
}

const props = defineProps<{ orders: TablePage<Row>; state: TableState; statuses: string[]; shipping_methods: FilterOption[] }>();
const { t } = useTrans();

const columns = computed<Column[]>(() => [
    { key: 'number', label: t('orders.number'), sortable: true, fixed: true },
    { key: 'customer_name', label: t('customers.customer'), sortable: true },
    { key: 'city', label: t('admin.city') },
    { key: 'items_count', label: t('admin.items'), sortable: true, align: 'center' },
    { key: 'channel', label: t('admin.channel'), hidden: true },
    { key: 'payment', label: t('admin.payment'), hidden: true },
    { key: 'shipping', label: t('admin.shipping_methods'), hidden: true },
    { key: 'status', label: t('common.status'), sortable: true },
    { key: 'total', label: t('admin.total'), sortable: true, align: 'end' },
    { key: 'placed_at', label: t('admin.date'), sortable: true, align: 'end' },
]);

const filters = computed<Filter[]>(() => [
    { key: 'status', label: t('common.status'), type: 'select', options: props.statuses.map((s) => ({ value: s, label: t(`orders.status_${s}`) })) },
    { key: 'channel', label: t('admin.channel'), type: 'select', options: [{ value: 'web', label: t('admin.channel_web') }, { value: 'pos', label: t('admin.channel_pos') }] },
    { key: 'account', label: t('customers.account'), type: 'select', options: [{ value: 'registered', label: t('customers.registered_account') }, { value: 'guest', label: t('customers.guest') }] },
    { key: 'payment', label: t('admin.payment'), type: 'select', options: ['cod', 'on_account'].map((p) => ({ value: p, label: t(`admin.pay_${p}`) })) },
    { key: 'shipping', label: t('admin.shipping_methods'), type: 'select', options: props.shipping_methods },
    { key: 'date', label: t('admin.date'), type: 'date_range' },
]);
</script>

<template>
    <PageHeader :title="t('admin.orders')" />

    <DataTable :page="orders" :state="state" :columns="columns" :filters="filters" :url="route('admin.orders.index')" storage-key="orders" :search-placeholder="t('admin.search_orders')" :title="t('admin.orders')">
        <template #cell-number="{ row }">
            <Link :href="row.show_url" class="font-semibold text-primary hover:underline" dir="ltr">{{ row.number }}</Link>
            <span v-if="row.channel === 'pos'" class="ms-1.5 kt-badge kt-badge-sm kt-badge-outline">POS</span>
        </template>
        <template #cell-customer_name="{ row }">
            <div class="min-w-40">
                <component :is="row.customer_url ? Link : 'span'" :href="row.customer_url ?? undefined" :class="['font-medium text-mono', { 'hover:text-primary': row.customer_url }]">
                    {{ row.customer }}
                    <KtIcon v-if="row.customer_url" name="profile-circle" class="text-xs text-primary" :title="t('customers.registered_account')" />
                </component>
                <p class="text-xs text-muted-foreground" dir="ltr">{{ row.phone }}</p>
            </div>
        </template>
        <template #cell-city="{ row }"><span class="text-sm">{{ row.city ?? '—' }}</span></template>
        <template #cell-items_count="{ row }"><span class="tabular-nums">{{ row.items_count }}</span></template>
        <template #cell-channel="{ row }">{{ t(`admin.channel_${row.channel}`) }}</template>
        <template #cell-payment="{ row }"><span class="text-sm">{{ t(`admin.pay_${row.payment}`) }}</span></template>
        <template #cell-shipping="{ row }"><span class="text-sm">{{ row.shipping ?? '—' }}</span></template>
        <template #cell-status="{ row }"><OrderStatusBadge :status="row.status" /></template>
        <template #cell-total="{ row }"><span class="whitespace-nowrap font-semibold tabular-nums" dir="ltr">{{ row.total }}</span></template>
        <template #cell-placed_at="{ row }">
            <time class="whitespace-nowrap text-sm text-secondary-foreground" :title="row.date">{{ row.ago }}</time>
        </template>
        <template #actions="{ row }">
            <RowActions :view-url="row.show_url" :delete-url="row.delete_url" :label="row.number" :activity="{ type: 'order', id: row.id }" />
        </template>
    </DataTable>
</template>
