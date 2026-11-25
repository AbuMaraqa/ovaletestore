<script setup lang="ts" generic="T extends { id: number | string }">
import { computed, ref, watch } from 'vue';
import ColumnToggle from '@/Components/DataTable/ColumnToggle.vue';
import TableFilters from '@/Components/DataTable/TableFilters.vue';
import TablePagination from '@/Components/DataTable/TablePagination.vue';
import type { Column, Filter, TablePage, TableState } from '@/Components/DataTable/types';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { pageMeta, useDataTable } from '@/Composables/useDataTable';
import { useTrans } from '@/Composables/useTrans';

/**
 * Admin list screen (Filament-like on Metronic's datatable look): debounced search, filter panel with
 * active-filter chips, sortable headers, column visibility, per-page + pagination, optional row
 * selection for bulk actions. All state lives in the URL; the server filters, sorts and paginates.
 *
 * Slots: `cell-<key>` ({ row }), `actions` ({ row }), `bulk` ({ ids, clear }), `toolbar`, `empty`.
 */
const props = withDefaults(
    defineProps<{
        page: TablePage<T>;
        state: TableState;
        columns: Column[];
        url: string;
        filters?: Filter[];
        searchPlaceholder?: string;
        storageKey: string;
        selectable?: boolean;
        title?: string;
    }>(),
    { filters: () => [], searchPlaceholder: undefined, selectable: false, title: undefined },
);

const { t } = useTrans();
const table = useDataTable({ url: () => props.url, state: () => props.state, columns: () => props.columns, storageKey: props.storageKey });
const meta = computed(() => pageMeta(props.page));

const sortField = (c: Column) => (typeof c.sortable === 'string' ? c.sortable : c.key);
const sortState = (c: Column) => (table.sort.value === sortField(c) ? 'asc' : table.sort.value === `-${sortField(c)}` ? 'desc' : null);
const alignClass = (c: Column) => ({ start: 'text-start', center: 'text-center', end: 'text-end' })[c.align ?? 'start'];

// Active filters as removable chips.
const optionLabel = (f: Filter, v: string) => (f.type === 'select' ? (f.options.find((o) => String(o.value) === v)?.label ?? v) : v);
const chips = computed(() =>
    props.filters.flatMap((f) =>
        f.type === 'date_range'
            ? (['from', 'until'] as const).filter((k) => table.filters[k]).map((k) => ({ key: k, text: `${f.label} ${t(`table.${k}`)}: ${table.filters[k]}` }))
            : table.filters[f.key] ? [{ key: f.key, text: `${f.label}: ${optionLabel(f, table.filters[f.key])}` }] : [],
    ),
);

// Row selection (kept only for rows on the current page).
const selected = ref<Array<number | string>>([]);
watch(() => props.page.data, () => (selected.value = []));
const allSelected = computed(() => props.page.data.length > 0 && selected.value.length === props.page.data.length);
const toggleAll = () => (selected.value = allSelected.value ? [] : props.page.data.map((r) => r.id));
const toggleRow = (id: number | string) => (selected.value = selected.value.includes(id) ? selected.value.filter((x) => x !== id) : [...selected.value, id]);

const colspan = computed(() => table.visibleColumns.value.length + (props.selectable ? 1 : 0) + 1);
</script>

<template>
    <section class="kt-card kt-card-grid min-w-full">
        <header class="kt-card-header flex-wrap gap-3 py-4">
            <div class="flex items-center gap-2.5">
                <h3 v-if="title" class="kt-card-title">{{ title }}</h3>
                <span v-if="title" class="kt-badge kt-badge-sm kt-badge-outline tabular-nums">{{ meta.total }}</span>
            </div>
            <div class="flex grow flex-wrap items-center justify-end gap-2.5">
                <label class="kt-input w-full max-w-[280px] sm:w-[280px]">
                    <KtIcon name="magnifier" />
                    <input v-model="table.search.value" type="search" :placeholder="searchPlaceholder ?? t('table.search')" :aria-label="t('common.search')" />
                </label>
                <slot name="toolbar" />
                <TableFilters v-if="filters.length" :filters="filters" :values="table.filters" :count="table.activeFilterCount.value" @change="table.setFilter" @reset="table.resetFilters" />
                <ColumnToggle :columns="columns" :hidden="table.hidden.value" @toggle="table.toggleColumn" />
            </div>
        </header>

        <div v-if="chips.length || (selectable && selected.length)" class="flex flex-wrap items-center gap-2 border-b border-border px-5 py-2.5">
            <template v-if="selectable && selected.length">
                <span class="text-sm font-medium text-mono">{{ t('table.selected', { count: selected.length }) }}</span>
                <slot name="bulk" :ids="selected" :clear="() => (selected = [])" />
                <span class="mx-1 h-5 border-s border-border" />
            </template>
            <button v-for="chip in chips" :key="chip.key" type="button" class="kt-badge kt-badge-outline kt-badge-primary gap-1.5" :aria-label="t('table.remove_filter', { filter: chip.text })" @click="table.setFilter(chip.key, '')">
                {{ chip.text }} <KtIcon name="cross" class="text-[10px]" />
            </button>
            <button v-if="chips.length" type="button" class="text-xs font-medium text-muted-foreground hover:text-destructive" @click="table.resetFilters">{{ t('table.reset') }}</button>
        </div>

        <div class="kt-card-table relative" :aria-busy="table.loading.value">
            <div v-if="table.loading.value" class="absolute inset-x-0 top-0 z-10 h-0.5 overflow-hidden bg-primary/15" aria-hidden="true"><div class="h-full w-1/3 animate-[dt-progress_1s_ease-in-out_infinite] bg-primary" /></div>
            <p class="sr-only" aria-live="polite">{{ table.loading.value ? t('table.loading') : t('table.range', { from: meta.from ?? 0, to: meta.to ?? 0, total: meta.total }) }}</p>
            <div :class="['kt-table-wrapper kt-scrollable overflow-x-auto transition-opacity', { 'pointer-events-none opacity-60': table.loading.value }]">
                <table class="kt-table kt-table-border align-middle text-sm">
                    <thead>
                        <tr class="bg-muted/40 text-[13px] text-secondary-foreground [&>th]:font-normal [&>th]:whitespace-nowrap">
                            <th v-if="selectable" class="w-12">
                                <input type="checkbox" class="kt-checkbox kt-checkbox-sm" :checked="allSelected" :aria-label="t('table.select_all')" @change="toggleAll" />
                            </th>
                            <th v-for="column in table.visibleColumns.value" :key="column.key" :class="[alignClass(column), column.class]" :aria-sort="sortState(column) === 'asc' ? 'ascending' : sortState(column) === 'desc' ? 'descending' : undefined">
                                <button v-if="column.sortable" type="button" class="inline-flex items-center gap-1.5 hover:text-primary" @click="table.toggleSort(sortField(column))">
                                    {{ column.label }}
                                    <KtIcon :name="sortState(column) === 'asc' ? 'arrow-up' : sortState(column) === 'desc' ? 'arrow-down' : 'arrow-up-down'" :class="['text-xs', sortState(column) ? 'text-primary' : 'text-muted-foreground/70']" />
                                </button>
                                <template v-else>{{ column.label }}</template>
                            </th>
                            <th class="w-px text-end"><span class="sr-only">{{ t('table.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in page.data" :key="row.id" :class="['hover:bg-muted/30', { 'bg-primary/5': selected.includes(row.id) }]">
                            <td v-if="selectable">
                                <input type="checkbox" class="kt-checkbox kt-checkbox-sm" :checked="selected.includes(row.id)" :aria-label="t('table.select_row')" @change="toggleRow(row.id)" />
                            </td>
                            <td v-for="column in table.visibleColumns.value" :key="column.key" :class="[alignClass(column), column.class]">
                                <slot :name="`cell-${column.key}`" :row="row">{{ (row as Record<string, unknown>)[column.key] ?? '—' }}</slot>
                            </td>
                            <td class="whitespace-nowrap text-end"><slot name="actions" :row="row" /></td>
                        </tr>
                        <tr v-if="!page.data.length">
                            <td :colspan="colspan" class="py-16">
                                <slot name="empty">
                                    <div class="flex flex-col items-center gap-3 text-center">
                                        <span class="flex size-14 items-center justify-center rounded-full bg-muted"><KtIcon name="search-list" class="text-2xl text-muted-foreground" /></span>
                                        <p class="font-medium text-mono">{{ t('table.empty') }}</p>
                                        <button v-if="chips.length || table.search.value" type="button" class="kt-btn kt-btn-sm kt-btn-outline" @click="table.resetFilters">{{ t('table.reset') }}</button>
                                    </div>
                                </slot>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <footer class="kt-card-footer">
            <TablePagination :meta="meta" :per-page="table.perPage.value" @per-page="table.setPerPage" />
        </footer>
    </section>
</template>
