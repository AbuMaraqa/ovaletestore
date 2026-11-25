<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import RowActions from '@/Components/DataTable/RowActions.vue';
import type { Column, Filter, TablePage, TableState } from '@/Components/DataTable/types';
import KtButton from '@/Components/UI/KtButton.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

interface Row {
    id: number;
    name: string;
    label: string;
    users_count: number;
    permissions_count: number;
    built_in: boolean;
    locked: boolean;
    created_at: string | null;
}

defineProps<{ roles: TablePage<Row>; state: TableState; total_permissions: number }>();
const { t } = useTrans();
const { can } = useCan();

const columns = computed<Column[]>(() => [
    { key: 'name', label: t('permissions.role'), sortable: true, fixed: true },
    { key: 'permissions_count', label: t('permissions.permissions_count'), sortable: true },
    { key: 'users_count', label: t('permissions.users_count'), sortable: true, align: 'center' },
    { key: 'type', label: t('permissions.type') },
]);
const filters = computed<Filter[]>(() => [
    { key: 'type', label: t('permissions.type'), type: 'select', options: [
        { value: 'built_in', label: t('permissions.built_in') },
        { value: 'custom', label: t('permissions.custom') },
    ] },
]);
</script>

<template>
    <PageHeader :title="t('permissions.title')" :description="t('permissions.subtitle')">
        <template #actions>
            <KtButton v-if="can('roles.create')" :href="route('admin.roles.create')" icon="plus">{{ t('permissions.new_role') }}</KtButton>
        </template>
    </PageHeader>

    <DataTable :page="roles" :state="state" :columns="columns" :filters="filters" :url="route('admin.roles.index')" storage-key="roles" :title="t('permissions.roles_list')">
        <template #cell-name="{ row }">
            <div class="flex items-center gap-3">
                <span :class="['flex size-9 items-center justify-center rounded-lg', row.locked ? 'bg-amber-500/10 text-amber-600' : 'bg-primary/10 text-primary']">
                    <KtIcon :name="row.locked ? 'crown' : 'security-user'" class="text-lg" />
                </span>
                <div>
                    <Link v-if="can('roles.view')" :href="route('admin.roles.edit', row.id)" class="font-medium text-mono hover:text-primary">{{ row.label }}</Link>
                    <span class="block font-mono text-xs text-muted-foreground" dir="ltr">{{ row.name }}</span>
                </div>
            </div>
        </template>
        <template #cell-permissions_count="{ row }">
            <div class="flex min-w-40 items-center gap-2.5">
                <div class="h-1.5 grow overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full bg-primary" :style="{ width: `${Math.round((row.permissions_count / total_permissions) * 100)}%` }" />
                </div>
                <span class="text-xs tabular-nums text-secondary-foreground">{{ t('permissions.selected_of', { count: row.permissions_count, total: total_permissions }) }}</span>
            </div>
        </template>
        <template #cell-users_count="{ row }"><span class="tabular-nums">{{ row.users_count }}</span></template>
        <template #cell-type="{ row }">
            <span :class="['kt-badge kt-badge-sm', row.built_in ? 'kt-badge-outline' : 'kt-badge-primary kt-badge-outline']">{{ row.built_in ? t('permissions.built_in') : t('permissions.custom') }}</span>
        </template>
        <template #actions="{ row }">
            <RowActions
                :edit-url="!row.locked && can('roles.update') ? route('admin.roles.edit', row.id) : null"
                :view-url="row.locked && can('roles.view') ? route('admin.roles.edit', row.id) : null"
                :delete-url="!row.built_in && can('roles.delete') ? route('admin.roles.destroy', row.id) : null"
                :label="row.label"
                :activity="{ type: 'role', id: row.id }"
            />
        </template>
    </DataTable>
</template>
