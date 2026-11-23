<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import KtBadge from '@/Components/UI/KtBadge.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtPagination from '@/Components/UI/KtPagination.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useDebounce } from '@/Composables/useDebounce';
import { useTrans } from '@/Composables/useTrans';
import type { PaginationLink } from '@/types';

interface Row { id: number; name: string; contact_name: string | null; phone: string | null; city: string | null; is_active: boolean; orders_count: number; balance: string; has_debt: boolean }
const props = defineProps<{
    companies: { data: Row[]; links: PaginationLink[]; total: number; from: number | null; to: number | null };
    outstanding: string;
    search: string;
}>();

const { t } = useTrans();
const search = ref(props.search);
watch(search, useDebounce(() => router.get(route('admin.companies.index'), search.value ? { search: search.value } : {}, { preserveState: true, replace: true }), 350));
</script>

<template>
    <PageHeader :title="t('admin.companies')" :description="`${t('admin.outstanding_total')}: ${outstanding}`">
        <template #actions><KtButton :href="route('admin.companies.create')" icon="plus">{{ t('admin.new_company') }}</KtButton></template>
    </PageHeader>
    <KtCard flush>
        <template #toolbar>
            <input v-model="search" type="search" class="kt-input w-72" :placeholder="t('admin.search_companies')" :aria-label="t('common.search')" />
        </template>
        <div class="kt-table-wrapper overflow-x-auto">
            <table class="kt-table">
                <thead>
                    <tr>
                        <th>{{ t('admin.company') }}</th>
                        <th>{{ t('admin.contact_name') }}</th>
                        <th>{{ t('admin.phone') }}</th>
                        <th class="text-center">{{ t('admin.orders') }}</th>
                        <th class="text-end">{{ t('admin.balance') }}</th>
                        <th>{{ t('common.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in companies.data" :key="c.id">
                        <td>
                            <Link :href="route('admin.companies.show', c.id)" class="font-medium text-mono hover:text-primary">{{ c.name }}</Link>
                            <span v-if="c.city" class="block text-xs text-muted-foreground">{{ c.city }}</span>
                        </td>
                        <td class="text-sm">{{ c.contact_name ?? '—' }}</td>
                        <td class="text-sm" dir="ltr">{{ c.phone ?? '—' }}</td>
                        <td class="text-center tabular-nums">{{ c.orders_count }}</td>
                        <td class="text-end font-semibold tabular-nums" :class="c.has_debt ? 'text-destructive' : 'text-muted-foreground'" dir="ltr">{{ c.balance }}</td>
                        <td><KtBadge :variant="c.is_active ? 'success' : 'secondary'">{{ c.is_active ? t('admin.active') : t('admin.inactive') }}</KtBadge></td>
                    </tr>
                    <tr v-if="!companies.data.length"><td colspan="6" class="py-12 text-center text-muted-foreground">{{ t('admin.no_companies') }}</td></tr>
                </tbody>
            </table>
        </div>
        <template #footer><KtPagination :links="companies.links" :total="companies.total" :from="companies.from" :to="companies.to" class="w-full" /></template>
    </KtCard>
</template>
