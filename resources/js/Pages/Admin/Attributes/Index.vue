<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import RowActions from '@/Components/DataTable/RowActions.vue';
import type { Column, Filter, TablePage, TableState } from '@/Components/DataTable/types';
import KtBadge from '@/Components/UI/KtBadge.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

interface Row { id: number; name: string; code: string; display_type: string; terms: string[]; terms_count: number; edit_url: string | null; delete_url: string | null }

const props = defineProps<{ attributes: TablePage<Row>; state: TableState; display_types: string[] }>();
const { t } = useTrans();
const { can } = useCan();

const displayLabel = (type: string) => {
    const key = `admin.display_${type}`;
    return t(key) === key ? type : t(key);
};
const columns = computed<Column[]>(() => [
    { key: 'name', label: t('admin.name'), fixed: true },
    { key: 'code', label: t('admin.code'), sortable: true },
    { key: 'display_type', label: t('admin.type'), hidden: true },
    { key: 'terms_count', label: t('admin.terms'), sortable: true },
]);
const filters = computed<Filter[]>(() => [
    { key: 'display', label: t('admin.type'), type: 'select', options: props.display_types.map((d) => ({ value: d, label: displayLabel(d) })) },
]);
</script>

<template>
    <PageHeader :title="t('admin.attributes')">
        <template #actions><KtButton v-if="can('attributes.create')" :href="route('admin.attributes.create')" icon="plus">{{ t('admin.new_attribute') }}</KtButton></template>
    </PageHeader>

    <DataTable :page="attributes" :state="state" :columns="columns" :filters="filters" :url="route('admin.attributes.index')" storage-key="attributes" :search-placeholder="t('admin.search_attributes')" :title="t('admin.attributes')">
        <template #cell-name="{ row }">
            <component :is="row.edit_url ? Link : 'span'" :href="row.edit_url ?? undefined" class="font-medium text-mono hover:text-primary">{{ row.name }}</component>
        </template>
        <template #cell-code="{ row }"><span class="font-mono text-xs text-muted-foreground" dir="ltr">{{ row.code }}</span></template>
        <template #cell-display_type="{ row }">{{ displayLabel(row.display_type) }}</template>
        <template #cell-terms_count="{ row }">
            <div class="flex max-w-lg flex-wrap gap-1">
                <KtBadge v-for="term in row.terms.slice(0, 8)" :key="term">{{ term }}</KtBadge>
                <span v-if="row.terms_count > 8" class="text-xs text-muted-foreground">+{{ row.terms_count - 8 }}</span>
            </div>
        </template>
        <template #actions="{ row }">
            <RowActions :edit-url="row.edit_url" :delete-url="row.delete_url" :label="row.name" :activity="{ type: 'attribute', id: row.id }" />
        </template>
    </DataTable>
</template>
