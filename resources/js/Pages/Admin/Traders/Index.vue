<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import TraderReviewModal from '@/Components/Admin/Traders/TraderReviewModal.vue';
import type { FilterOption, TablePage, TableState } from '@/Components/DataTable/types';
import ResourceTable from '@/Components/Table/ResourceTable.vue';
import type { TableDef } from '@/Components/Table/types';
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

/** Trader requests: App\Tables\Admin\TradersTable (bulk approve per tier / reject) + the one-by-one review modal. */
defineProps<{ traders: TablePage<Row>; state: TableState; table: TableDef; tiers: FilterOption[]; pending: number }>();
const { t } = useTrans();
const { can } = useCan();
const reviewing = ref<Row | null>(null);
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

    <ResourceTable :table="table" :page="traders" :state="state">
        <template #row-actions="{ row }">
            <button v-if="row.can_review" type="button" :class="['kt-btn kt-btn-sm', row.status === 'pending' ? 'kt-btn-primary' : 'kt-btn-outline']" @click="reviewing = row">
                {{ row.status === 'pending' ? t('traders.review') : t('traders.change_tier') }}
            </button>
        </template>
    </ResourceTable>

    <TraderReviewModal :trader="reviewing" :tiers="tiers" @close="reviewing = null" />
</template>
