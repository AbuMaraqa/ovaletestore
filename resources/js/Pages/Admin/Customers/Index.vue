<script setup lang="ts">
import type { TablePage, TableState } from '@/Components/DataTable/types';
import ResourceTable from '@/Components/Table/ResourceTable.vue';
import type { TableDef } from '@/Components/Table/types';
import KtButton from '@/Components/UI/KtButton.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

/** Customers: KPI cards + the server-defined table (App\Tables\Admin\CustomersTable). */
defineProps<{ customers: TablePage<{ id: number }>; state: TableState; table: TableDef; stats: { total: number; with_orders: number; new_this_month: number } }>();
const { t } = useTrans();
const { can } = useCan();

const cards = [
    { key: 'total', icon: 'profile-circle', tone: 'bg-primary/10 text-primary' },
    { key: 'with_orders', icon: 'handcart', tone: 'bg-green-500/10 text-green-600' },
    { key: 'new_this_month', icon: 'calendar-tick', tone: 'bg-sky-500/10 text-sky-600' },
] as const;
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

    <ResourceTable :table="table" :page="customers" :state="state" />
</template>
