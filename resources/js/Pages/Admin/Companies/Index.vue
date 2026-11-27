<script setup lang="ts">
import type { TablePage, TableState } from '@/Components/DataTable/types';
import ResourceTable from '@/Components/Table/ResourceTable.vue';
import type { TableDef } from '@/Components/Table/types';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

interface Row { id: number; balance: string; has_debt: boolean }

/** Server-defined table: App\Tables\Admin\CompaniesTable. The balance cell is red when the company owes money. */
defineProps<{ companies: TablePage<Row>; state: TableState; table: TableDef; outstanding: string }>();
const { t } = useTrans();
const { can } = useCan();
</script>

<template>
    <PageHeader :title="t('admin.companies')" :description="`${t('admin.outstanding_total')}: ${outstanding}`">
        <template #actions><KtButton v-if="can('companies.create')" :href="route('admin.companies.create')" icon="plus">{{ t('admin.new_company') }}</KtButton></template>
    </PageHeader>

    <ResourceTable :table="table" :page="companies" :state="state">
        <template #cell-balance="{ row }">
            <span :class="['whitespace-nowrap font-semibold tabular-nums', row.has_debt ? 'text-destructive' : 'text-muted-foreground']" dir="ltr">{{ row.balance }}</span>
        </template>
    </ResourceTable>
</template>
