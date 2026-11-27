<script setup lang="ts">
import type { TablePage, TableState } from '@/Components/DataTable/types';
import ResourceTable from '@/Components/Table/ResourceTable.vue';
import type { TableDef } from '@/Components/Table/types';
import KtButton from '@/Components/UI/KtButton.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

/** Server-defined table: App\Tables\Admin\UsersTable (columns, filters, bulk actions, export). */
defineProps<{ users: TablePage<{ id: number }>; state: TableState; table: TableDef }>();
const { t } = useTrans();
const { can } = useCan();
</script>

<template>
    <PageHeader :title="t('admin.users')">
        <template #actions>
            <KtButton v-if="can('roles.view')" :href="route('admin.roles.index')" variant="outline" icon="security-user">{{ t('admin.roles') }}</KtButton>
            <KtButton v-if="can('users.create')" :href="route('admin.users.create')" icon="plus">{{ t('admin.new_user') }}</KtButton>
        </template>
    </PageHeader>

    <ResourceTable :table="table" :page="users" :state="state" />
</template>
