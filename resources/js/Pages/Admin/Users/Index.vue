<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import RowActions from '@/Components/DataTable/RowActions.vue';
import type { Column, Filter, FilterOption, TablePage, TableState } from '@/Components/DataTable/types';
import KtBadge from '@/Components/UI/KtBadge.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';
import type { Avatar } from '@/types';

interface Row {
    id: number; name: string; email: string; avatar: Avatar; is_active: boolean; roles: string[]; two_factor: boolean;
    created_at: string | null; edit_url: string | null; delete_url: string | null;
}

const props = defineProps<{ users: TablePage<Row>; state: TableState; roles: FilterOption[] }>();
const { t } = useTrans();
const { can } = useCan();

const roleLabel = (name: string) => props.roles.find((r) => r.value === name)?.label ?? t('permissions.roles.super-admin');
const columns = computed<Column[]>(() => [
    { key: 'name', label: t('admin.name'), sortable: true, fixed: true },
    { key: 'roles', label: t('admin.roles_assigned') },
    { key: 'is_active', label: t('common.status') },
    { key: 'two_factor', label: t('admin.two_factor'), hidden: true },
    { key: 'created_at', label: t('admin.created'), sortable: true, align: 'end' },
]);
const filters = computed<Filter[]>(() => [
    { key: 'role', label: t('admin.role'), type: 'select', options: props.roles },
    { key: 'status', label: t('common.status'), type: 'select', options: [{ value: 'active', label: t('admin.active') }, { value: 'inactive', label: t('admin.inactive') }] },
    { key: 'two_factor', label: t('admin.two_factor'), type: 'select', options: [{ value: 'on', label: t('admin.enabled') }, { value: 'off', label: t('admin.disabled') }] },
]);
</script>

<template>
    <PageHeader :title="t('admin.users')">
        <template #actions>
            <KtButton v-if="can('roles.view')" :href="route('admin.roles.index')" variant="outline" icon="security-user">{{ t('admin.roles') }}</KtButton>
            <KtButton v-if="can('users.create')" :href="route('admin.users.create')" icon="plus">{{ t('admin.new_user') }}</KtButton>
        </template>
    </PageHeader>

    <DataTable :page="users" :state="state" :columns="columns" :filters="filters" :url="route('admin.users.index')" storage-key="users" :search-placeholder="t('admin.search_users')" :title="t('admin.staff')">
        <template #cell-name="{ row }">
            <div class="flex items-center gap-3">
                <UserAvatar :avatar="row.avatar" :name="row.name" :ring="false" />
                <div class="min-w-0">
                    <component :is="row.edit_url ? Link : 'span'" :href="row.edit_url ?? undefined" class="font-medium text-mono hover:text-primary">{{ row.name }}</component>
                    <p class="truncate text-xs text-muted-foreground" dir="ltr">{{ row.email }}</p>
                </div>
            </div>
        </template>
        <template #cell-roles="{ row }">
            <div class="flex flex-wrap gap-1"><KtBadge v-for="r in row.roles" :key="r" variant="primary">{{ roleLabel(r) }}</KtBadge></div>
        </template>
        <template #cell-is_active="{ row }"><KtBadge :variant="row.is_active ? 'success' : 'secondary'">{{ row.is_active ? t('admin.active') : t('admin.inactive') }}</KtBadge></template>
        <template #cell-two_factor="{ row }"><KtBadge :variant="row.two_factor ? 'info' : 'secondary'">{{ row.two_factor ? t('admin.enabled') : t('admin.disabled') }}</KtBadge></template>
        <template #cell-created_at="{ row }"><span class="text-sm text-muted-foreground">{{ row.created_at }}</span></template>
        <template #actions="{ row }">
            <RowActions :edit-url="row.edit_url" :delete-url="row.delete_url" :label="row.name" :activity="{ type: 'user', id: row.id }" />
        </template>
    </DataTable>
</template>
