<script setup lang="ts">
import type { TablePage, TableState } from '@/Components/DataTable/types';
import ResourceTable from '@/Components/Table/ResourceTable.vue';
import type { TableDef } from '@/Components/Table/types';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

/** Server-defined table: App\Tables\Admin\CategoriesTable (columns, filters, bulk actions, export). */
defineProps<{ categories: TablePage<{ id: number }>; state: TableState; table: TableDef }>();
const { t } = useTrans();
const { can } = useCan();
</script>

<template>
    <PageHeader :title="t('admin.categories')">
        <template #actions>
            <KtButton v-if="can('categories.create')" :href="route('admin.categories.create')" icon="plus">{{ t('admin.new_category') }}</KtButton>
        </template>
    </PageHeader>

    <ResourceTable :table="table" :page="categories" :state="state" />
</template>
