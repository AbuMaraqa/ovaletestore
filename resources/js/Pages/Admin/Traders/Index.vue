<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TraderReviewModal from '@/Components/Admin/Traders/TraderReviewModal.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import RowActions from '@/Components/DataTable/RowActions.vue';
import type { Column, Filter, FilterOption, TablePage, TableState } from '@/Components/DataTable/types';
import KtButton from '@/Components/UI/KtButton.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';
import type { Avatar } from '@/types';

interface Row {
    id: number; name: string; business_name: string | null; email: string; phone: string | null; avatar: Avatar;
    status: 'pending' | 'approved' | 'rejected'; tier_id: number | null; tier: string | null;
    orders_count: number; spent: string; requested: string | null; show_url: string | null; can_review: boolean;
}

const props = defineProps<{ traders: TablePage<Row>; state: TableState; tiers: FilterOption[]; pending: number }>();
const { t } = useTrans();
const { can } = useCan();
const reviewing = ref<Row | null>(null);

const columns = computed<Column[]>(() => [
    { key: 'name', label: t('traders.trader'), sortable: true, fixed: true },
    { key: 'business_name', label: t('traders.business_name'), sortable: true },
    { key: 'tier', label: t('traders.tier') },
    { key: 'status', label: t('common.status') },
    { key: 'orders_count', label: t('customers.orders'), sortable: true, align: 'center' },
    { key: 'spent', label: t('customers.spent'), sortable: true, align: 'end', hidden: true },
    { key: 'trader_requested_at', label: t('traders.requested'), sortable: true, align: 'end' },
]);
const filters = computed<Filter[]>(() => [
    { key: 'status', label: t('common.status'), type: 'select', options: ['pending', 'approved', 'rejected'].map((s) => ({ value: s, label: t(`traders.status_${s}`) })) },
    { key: 'tier', label: t('traders.tier'), type: 'select', options: props.tiers },
]);
const tone = { pending: 'kt-badge-warning', approved: 'kt-badge-success', rejected: 'kt-badge-destructive' } as const;
</script>

<template>
    <PageHeader :title="t('traders.title')" :description="t('traders.subtitle')">
        <template #actions>
            <KtButton v-if="can('price_tiers.view')" :href="route('admin.price-tiers.index')" variant="outline" icon="price-tag">{{ t('traders.tiers') }}</KtButton>
        </template>
    </PageHeader>

    <div v-if="pending" class="mb-5 flex items-center gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-amber-800 dark:text-amber-200">
        <KtIcon name="notification-status" class="text-xl" />
        <p class="grow text-sm">{{ t('traders.pending_banner', { count: pending }) }}</p>
        <Link :href="route('admin.traders.index', { filter: { status: 'pending' } })" class="kt-btn kt-btn-sm kt-btn-outline">{{ t('traders.show_pending') }}</Link>
    </div>

    <DataTable :page="traders" :state="state" :columns="columns" :filters="filters" :url="route('admin.traders.index')" storage-key="traders" :search-placeholder="t('customers.search')">
        <template #cell-name="{ row }">
            <div class="flex items-center gap-3">
                <UserAvatar :avatar="row.avatar" :name="row.name" :ring="false" />
                <div class="min-w-0">
                    <component :is="row.show_url ? Link : 'span'" :href="row.show_url ?? undefined" class="font-medium text-mono hover:text-primary">{{ row.name }}</component>
                    <p class="truncate text-xs text-secondary-foreground"><bdi dir="ltr">{{ row.phone ?? row.email }}</bdi></p>
                </div>
            </div>
        </template>
        <template #cell-business_name="{ row }"><span class="text-sm">{{ row.business_name ?? '—' }}</span></template>
        <template #cell-tier="{ row }"><span v-if="row.tier" class="kt-badge kt-badge-sm kt-badge-primary kt-badge-outline">{{ row.tier }}</span><span v-else class="text-muted-foreground">—</span></template>
        <template #cell-status="{ row }"><span :class="['kt-badge kt-badge-sm kt-badge-outline', tone[row.status]]">{{ t(`traders.status_${row.status}`) }}</span></template>
        <template #cell-orders_count="{ row }"><span class="tabular-nums">{{ row.orders_count }}</span></template>
        <template #cell-spent="{ row }"><bdi dir="ltr" class="font-medium tabular-nums">{{ row.spent }}</bdi></template>
        <template #cell-trader_requested_at="{ row }"><span class="text-sm text-secondary-foreground">{{ row.requested ?? '—' }}</span></template>
        <template #actions="{ row }">
            <RowActions :view-url="row.show_url" :label="row.name" :activity="{ type: 'user', id: row.id }">
                <button v-if="row.can_review" type="button" :class="['kt-btn kt-btn-sm', row.status === 'pending' ? 'kt-btn-primary' : 'kt-btn-outline']" @click="reviewing = row">
                    {{ row.status === 'pending' ? t('traders.review') : t('traders.change_tier') }}
                </button>
            </RowActions>
        </template>
    </DataTable>

    <TraderReviewModal :trader="reviewing" :tiers="tiers" @close="reviewing = null" />
</template>
