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

interface Row { id: number; symbol: string; name: string; code: string; is_default: boolean; edit_url: string | null }

/** Currencies: App\Tables\Admin\CurrenciesTable. "Make default" is a confirmed row action defined on the server. */
defineProps<{ currencies: TablePage<Row>; state: TableState; table: TableDef }>();
const { t } = useTrans();
const { can } = useCan();
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

    <ResourceTable :table="table" :page="currencies" :state="state">
        <template #cell-name="{ row }">
            <div class="flex items-center gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 font-semibold text-primary" dir="ltr">{{ row.symbol }}</span>
                <div class="min-w-0">
                    <component :is="row.edit_url ? Link : 'span'" :href="row.edit_url ?? undefined" class="font-medium text-mono hover:text-primary">{{ row.name }}</component>
                    <span class="block font-mono text-xs text-secondary-foreground" dir="ltr">{{ row.code }}</span>
                </div>
                <span v-if="row.is_default" class="kt-badge kt-badge-sm kt-badge-primary">{{ t('currencies.default') }}</span>
            </div>
        </template>
    </ResourceTable>
</template>
