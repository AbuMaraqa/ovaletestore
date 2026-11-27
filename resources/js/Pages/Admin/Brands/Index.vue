<script setup lang="ts">
import type { TablePage, TableState } from '@/Components/DataTable/types';
import ResourceTable from '@/Components/Table/ResourceTable.vue';
import type { TableDef } from '@/Components/Table/types';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

/** Server-defined table: App\Tables\Admin\BrandsTable (columns, filters, bulk actions, export). */
defineProps<{ brands: TablePage<{ id: number }>; state: TableState; table: TableDef }>();
const { t } = useTrans();
const { can } = useCan();
</script>

<template>
    <PageHeader :title="t('admin.brands')">
        <template #actions>
            <KtButton v-if="can('brands.create')" :href="route('admin.brands.create')" icon="plus">{{ t('admin.new_brand') }}</KtButton>
        </template>
    </PageHeader>

    <ResourceTable :table="table" :page="brands" :state="state" />
</template>
