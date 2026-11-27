<script setup lang="ts">
import type { TablePage, TableState } from '@/Components/DataTable/types';
import ResourceTable from '@/Components/Table/ResourceTable.vue';
import type { TableDef } from '@/Components/Table/types';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

/** Server-defined table: App\Tables\Admin\ShippingMethodsTable (columns, filters, bulk actions, export). */
defineProps<{ methods: TablePage<{ id: number }>; state: TableState; table: TableDef }>();
const { t } = useTrans();
const { can } = useCan();
</script>

<template>
    <PageHeader :title="t('admin.shipping_methods')" :description="t('admin.shipping_methods_help')">
        <template #actions>
            <KtButton v-if="can('shipping.create')" :href="route('admin.shipping-methods.create')" icon="plus">{{ t('admin.new_shipping_method') }}</KtButton>
        </template>
    </PageHeader>

    <ResourceTable :table="table" :page="methods" :state="state" />
</template>
