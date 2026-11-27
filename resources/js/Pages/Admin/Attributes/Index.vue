<script setup lang="ts">
import type { TablePage, TableState } from '@/Components/DataTable/types';
import ResourceTable from '@/Components/Table/ResourceTable.vue';
import type { TableDef } from '@/Components/Table/types';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

/** Server-defined table: App\Tables\Admin\AttributesTable (columns, filters, bulk actions, export). */
defineProps<{ attributes: TablePage<{ id: number }>; state: TableState; table: TableDef }>();
const { t } = useTrans();
const { can } = useCan();
</script>

<template>
    <PageHeader :title="t('admin.attributes')">
        <template #actions>
            <KtButton v-if="can('attributes.create')" :href="route('admin.attributes.create')" icon="plus">{{ t('admin.new_attribute') }}</KtButton>
        </template>
    </PageHeader>

    <ResourceTable :table="table" :page="attributes" :state="state" />
</template>
