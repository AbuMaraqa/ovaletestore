<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { TablePage, TableState } from '@/Components/DataTable/types';
import ResourceTable from '@/Components/Table/ResourceTable.vue';
import type { TableDef } from '@/Components/Table/types';
import KtButton from '@/Components/UI/KtButton.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

interface Row { id: number; name: string; label: string; locked: boolean; permissions_count: number; permissions_label: string; edit_url: string | null; view_url: string | null }

/** Roles: App\Tables\Admin\RolesTable. Custom cells: role icon and a permissions progress bar. */
const props = defineProps<{ roles: TablePage<Row>; state: TableState; table: TableDef; total_permissions: number }>();
const { t } = useTrans();
const { can } = useCan();

const percent = (row: Row) => Math.round((row.permissions_count / Math.max(props.total_permissions, 1)) * 100);
</script>

<template>
    <PageHeader :title="t('permissions.title')" :description="t('permissions.subtitle')">
        <template #actions>
            <KtButton v-if="can('roles.create')" :href="route('admin.roles.create')" icon="plus">{{ t('permissions.new_role') }}</KtButton>
        </template>
    </PageHeader>

    <ResourceTable :table="table" :page="roles" :state="state">
        <template #cell-name="{ row }">
            <div class="flex items-center gap-3">
                <span :class="['flex size-9 items-center justify-center rounded-lg', row.locked ? 'bg-amber-500/10 text-amber-600' : 'bg-primary/10 text-primary']">
                    <KtIcon :name="row.locked ? 'crown' : 'security-user'" class="text-lg" />
                </span>
                <div>
                    <component :is="row.edit_url ?? row.view_url ? Link : 'span'" :href="row.edit_url ?? row.view_url ?? undefined" class="font-medium text-mono hover:text-primary">{{ row.label }}</component>
                    <span class="block font-mono text-xs text-muted-foreground" dir="ltr">{{ row.name }}</span>
                </div>
            </div>
        </template>
        <template #cell-permissions_count="{ row }">
            <div class="flex min-w-40 items-center gap-2.5">
                <div class="h-1.5 grow overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full bg-primary" :style="{ width: `${percent(row)}%` }" />
                </div>
                <span class="text-xs tabular-nums text-secondary-foreground">{{ row.permissions_label }}</span>
            </div>
        </template>
    </ResourceTable>
</template>
