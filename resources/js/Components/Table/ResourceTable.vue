<script setup lang="ts" generic="T extends { id: number | string }">
import { router } from '@inertiajs/vue3';
import { computed, useSlots } from 'vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import RowActions from '@/Components/DataTable/RowActions.vue';
import type { TablePage, TableState } from '@/Components/DataTable/types';
import TableCell from '@/Components/Table/TableCell.vue';
import type { BulkDef, ExtraAction, TableDef } from '@/Components/Table/types';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useTrans } from '@/Composables/useTrans';

/**
 * Renders any server-defined table (App\Tables\Table): columns, filters, sorting, row actions, bulk actions
 * on the selection (every table) and CSV export (all filtered rows, or only the selection).
 * A page may still override a cell with a `cell-<key>` slot, and add row buttons with the `row-actions` slot.
 */
const props = defineProps<{ table: TableDef; page: TablePage<T>; state: TableState }>();
const slots = useSlots();
const { t } = useTrans();
const { confirm } = useConfirm();

const field = (row: T, name?: string) => (name ? ((row as Record<string, unknown>)[name] as string | null | undefined) ?? null : null);
const activity = (row: T) => (props.table.actions.activity ? { type: props.table.actions.activity, id: Number(row.id) } : null);
const extras = (row: T) => ((props.table.actions.extra ? (row as Record<string, unknown>)[props.table.actions.extra] : null) as ExtraAction[] | null) ?? [];
const customCells = computed(() => props.table.columns.filter((c) => slots[`cell-${c.key}`]).map((c) => c.key));
const selectable = computed(() => props.table.bulk.length > 0 || props.table.export_url !== null);

async function runBulk(bulk: BulkDef, ids: Array<number | string>, clear: () => void): Promise<void> {
    if (bulk.href) {
        window.open(`${bulk.href}${bulk.href.includes('?') ? '&' : '?'}ids=${ids.join(',')}`, '_blank', 'noopener');
        return;
    }
    if (bulk.confirm) {
        const ok = await confirm({
            title: t('table.bulk_confirm_title', { action: bulk.label, count: ids.length }),
            message: bulk.variant === 'destructive' ? t('common.confirm_delete') : t('table.bulk_confirm_text'),
            confirmLabel: bulk.label,
            danger: bulk.variant === 'destructive',
        });
        if (!ok) return;
    }
    router.post(props.table.bulk_url, { action: bulk.action, ids }, { preserveScroll: true, onSuccess: clear });
}

/** Export of the selection: the same CSV endpoint, limited to the selected ids. */
function exportSelected(ids: Array<number | string>): string {
    const url = new URL(props.table.export_url ?? '', window.location.origin);
    url.searchParams.set('filter[ids]', ids.join(','));
    return url.toString();
}

async function runExtra(action: ExtraAction): Promise<void> {
    if (action.confirm) {
        const ok = await confirm({ title: action.confirm.title, message: action.confirm.message, confirmLabel: action.confirm.confirm });
        if (!ok) return;
    }
    router.visit(action.href, { method: action.method ?? 'get', preserveScroll: true });
}
</script>

<template>
    <DataTable
        :page="page"
        :state="state"
        :columns="table.columns"
        :filters="table.filters"
        :url="table.url"
        :storage-key="table.key"
        :search-placeholder="table.search ?? undefined"
        :title="table.title"
        :selectable="selectable"
    >
        <template #toolbar>
            <slot name="toolbar" />
            <a v-if="table.export_url" :href="table.export_url" class="kt-btn kt-btn-outline" :title="t('table.export_all')">
                <KtIcon name="exit-down" />
                <span class="hidden sm:inline">{{ t('table.export') }}</span>
            </a>
        </template>

        <template #bulk="{ ids, clear }">
            <button
                v-for="bulk in table.bulk"
                :key="bulk.action"
                type="button"
                :class="['kt-btn kt-btn-sm', bulk.variant === 'destructive' ? 'kt-btn-destructive' : 'kt-btn-outline']"
                @click="runBulk(bulk, ids, clear)"
            >
                {{ bulk.label }}
            </button>
            <a v-if="table.export_url" :href="exportSelected(ids)" class="kt-btn kt-btn-sm kt-btn-outline">
                <KtIcon name="exit-down" />{{ t('table.export_selected') }}
            </a>
        </template>

        <template v-for="column in table.columns" :key="column.key" #[`cell-${column.key}`]="{ row }">
            <slot v-if="customCells.includes(column.key)" :name="`cell-${column.key}`" :row="row" />
            <TableCell v-else :column="column" :row="row" />
        </template>

        <template #actions="{ row }">
            <RowActions
                :view-url="field(row, table.actions.view)"
                :edit-url="field(row, table.actions.edit)"
                :delete-url="field(row, table.actions.delete)"
                :label="field(row, table.actions.label) ?? undefined"
                :activity="activity(row)"
            >
                <slot name="row-actions" :row="row" />
                <template v-for="extra in extras(row)" :key="extra.href">
                    <a
                        v-if="(extra.method ?? 'get') === 'get' && extra.target"
                        :href="extra.href"
                        :target="extra.target"
                        rel="noopener"
                        class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost text-secondary-foreground hover:text-primary"
                        :title="extra.label"
                        :aria-label="`${extra.label}: ${field(row, table.actions.label) ?? ''}`"
                    >
                        <KtIcon :name="extra.icon" />
                    </a>
                    <button
                        v-else
                        type="button"
                        class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost text-secondary-foreground hover:text-primary"
                        :title="extra.label"
                        :aria-label="`${extra.label}: ${field(row, table.actions.label) ?? ''}`"
                        @click="runExtra(extra)"
                    >
                        <KtIcon :name="extra.icon" />
                    </button>
                </template>
            </RowActions>
        </template>
    </DataTable>
</template>
