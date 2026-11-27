<script setup lang="ts">
import type { TablePage, TableState } from '@/Components/DataTable/types';
import ResourceTable from '@/Components/Table/ResourceTable.vue';
import type { TableDef } from '@/Components/Table/types';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

interface Row { id: number; in_stock: boolean; stock: number | null }

/**
 * Products: App\Tables\Admin\ProductsTable (filters, bulk publish / draft / archive / feature / delete, export).
 * The stock cell also shows the quantity when it is tracked.
 */
defineProps<{ products: TablePage<Row>; state: TableState; table: TableDef }>();
const { t } = useTrans();
const { can } = useCan();
</script>

<template>
    <PageHeader :title="t('admin.products')">
        <template #actions>
            <KtButton v-if="can('products.create')" :href="route('admin.products.create')" icon="plus">{{ t('admin.new_product') }}</KtButton>
        </template>
    </PageHeader>

    <ResourceTable :table="table" :page="products" :state="state">
        <template #cell-stock="{ row }">
            <span :class="['kt-badge kt-badge-sm kt-badge-outline whitespace-nowrap', row.in_stock ? 'kt-badge-success' : 'kt-badge-destructive']">
                {{ row.in_stock ? t('admin.in_stock') : t('admin.out_of_stock') }}<template v-if="row.stock !== null"> · {{ row.stock }}</template>
            </span>
        </template>
    </ResourceTable>
</template>
