<script setup lang="ts">
import type { TablePage, TableState } from '@/Components/DataTable/types';
import ResourceTable from '@/Components/Table/ResourceTable.vue';
import type { TableDef } from '@/Components/Table/types';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

/** Orders list: defined on the server (App\Tables\Admin\OrdersTable). */
defineProps<{ orders: TablePage<{ id: number }>; state: TableState; table: TableDef }>();
const { t } = useTrans();
</script>

<template>
    <PageHeader :title="t('admin.orders')" />
    <ResourceTable :table="table" :page="orders" :state="state" />
</template>
