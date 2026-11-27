<script setup lang="ts">
import type { TablePage, TableState } from '@/Components/DataTable/types';
import ResourceTable from '@/Components/Table/ResourceTable.vue';
import type { TableDef } from '@/Components/Table/types';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

/** Server-defined table: App\Tables\Admin\PriceTiersTable (columns, filters, bulk actions, export). */
defineProps<{ tiers: TablePage<{ id: number }>; state: TableState; table: TableDef }>();
const { t } = useTrans();
const { can } = useCan();
</script>

<template>
    <PageHeader :title="t('traders.tiers')" :description="t('traders.tiers_help')">
        <template #actions>
            <KtButton v-if="can('price_tiers.create')" :href="route('admin.price-tiers.create')" icon="plus">{{ t('traders.new_tier') }}</KtButton>
        </template>
    </PageHeader>

    <ResourceTable :table="table" :page="tiers" :state="state" />
</template>
