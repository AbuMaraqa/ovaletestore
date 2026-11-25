<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import RowActions from '@/Components/DataTable/RowActions.vue';
import type { Column, Filter, TablePage, TableState } from '@/Components/DataTable/types';
import KtBadge from '@/Components/UI/KtBadge.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useConfirm } from '@/Composables/useConfirm';
import { useTrans } from '@/Composables/useTrans';

interface Row {
    id: number; code: string; name: string; symbol: string; rate: number; value: string; sample: string; example: string; is_default: boolean; is_active: boolean;
    edit_url: string | null; delete_url: string | null; default_url: string | null;
}

const props = defineProps<{ currencies: TablePage<Row>; base: { code: string; sample: string }; state: TableState }>();
const { t } = useTrans();
const { can } = useCan();
const { confirm } = useConfirm();

const columns = computed<Column[]>(() => [
    { key: 'name', label: t('admin.name'), fixed: true },
    { key: 'rate', label: t('currencies.value_column'), sortable: true },
    { key: 'sample', label: t('currencies.sample', { amount: props.base.sample }) },
    { key: 'is_active', label: t('common.status') },
]);
const filters = computed<Filter[]>(() => [
    { key: 'status', label: t('common.status'), type: 'select', options: [{ value: 'active', label: t('admin.active') }, { value: 'inactive', label: t('admin.inactive') }] },
]);

/** Changing the base converts every stored price: always behind an explicit confirmation naming the rate. */
async function makeDefault(row: Row): Promise<void> {
    if (!row.default_url) return;
    const ok = await confirm({
        title: t('currencies.make_default_title', { code: row.code }),
        message: [t('currencies.make_default_message', { code: row.code, rate: row.value }), row.example, t('currencies.make_default_warning')].join('\n\n'),
        confirmLabel: t('currencies.make_default_confirm'),
    });
    if (ok) router.patch(row.default_url, {}, { preserveScroll: true });
}
</script>

<template>
    <PageHeader :title="t('currencies.title')" :description="t('currencies.help')">
        <template #actions>
            <KtButton v-if="can('currencies.create')" :href="route('admin.currencies.create')" icon="plus">{{ t('currencies.new') }}</KtButton>
        </template>
    </PageHeader>

    <div class="kt-card mb-5 flex-row items-start gap-3 p-4 text-sm text-secondary-foreground">
        <KtIcon name="information-2" class="mt-0.5 shrink-0 text-lg text-primary" />
        <p>{{ t('currencies.how_it_works') }}</p>
    </div>

    <DataTable :page="currencies" :state="state" :columns="columns" :filters="filters" :url="route('admin.currencies.index')" storage-key="currencies">
        <template #cell-name="{ row }">
            <div class="flex items-center gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 font-semibold text-primary" dir="ltr">{{ row.symbol }}</span>
                <div class="min-w-0">
                    <component :is="row.edit_url ? Link : 'span'" :href="row.edit_url ?? undefined" class="font-medium text-mono hover:text-primary">{{ row.name }}</component>
                    <span class="block font-mono text-xs text-secondary-foreground" dir="ltr">{{ row.code }}</span>
                </div>
                <KtBadge v-if="row.is_default" variant="primary">{{ t('currencies.default') }}</KtBadge>
            </div>
        </template>
        <template #cell-rate="{ row }">
            <span v-if="row.is_default" class="text-muted-foreground">—</span>
            <span v-else class="font-mono tabular-nums" dir="ltr">{{ row.value }}</span>
        </template>
        <template #cell-sample="{ row }"><span class="tabular-nums" dir="ltr">{{ row.sample }}</span></template>
        <template #cell-is_active="{ row }"><KtBadge :variant="row.is_active ? 'success' : 'secondary'">{{ row.is_active ? t('admin.active') : t('admin.inactive') }}</KtBadge></template>
        <template #actions="{ row }">
            <RowActions :edit-url="row.edit_url" :delete-url="row.delete_url" :label="row.name" :activity="{ type: 'currency', id: row.id }">
                <button
                    v-if="row.default_url"
                    type="button"
                    class="kt-btn kt-btn-sm kt-btn-ghost text-secondary-foreground hover:text-primary"
                    :aria-label="`${t('currencies.make_default')}: ${row.name}`"
                    @click="makeDefault(row)"
                >
                    <KtIcon name="star" />
                    <span class="hidden md:inline">{{ t('currencies.make_default') }}</span>
                </button>
            </RowActions>
        </template>
    </DataTable>
</template>
