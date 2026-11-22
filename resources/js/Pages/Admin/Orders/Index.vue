<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import OrderStatusBadge from '@/Components/Admin/OrderStatusBadge.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtPagination from '@/Components/UI/KtPagination.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useDebounce } from '@/Composables/useDebounce';
import { useTrans } from '@/Composables/useTrans';
import type { PaginationLink } from '@/types';

interface Row { id: number; number: string; customer: string; phone: string; city: string; status: string; items_count: number; total: string; date: string }
const props = defineProps<{
    orders: { data: Row[]; links: PaginationLink[]; total: number; from: number | null; to: number | null };
    filters: { filter?: { status?: string; search?: string } };
    statuses: string[];
}>();

const { t } = useTrans();
const search = ref(props.filters.filter?.search ?? '');
const status = ref(props.filters.filter?.status ?? '');
const reload = () =>
    router.get(route('admin.orders.index'), { filter: { ...(search.value ? { search: search.value } : {}), ...(status.value ? { status: status.value } : {}) } }, { preserveState: true, replace: true });
watch(search, useDebounce(reload, 350));
watch(status, reload);
</script>

<template>
    <PageHeader :title="t('admin.orders')" />
    <KtCard flush>
        <template #toolbar>
            <div class="flex flex-wrap gap-2">
                <input v-model="search" type="search" class="kt-input w-72" :placeholder="t('orders.search')" :aria-label="t('common.search')" />
                <select v-model="status" class="kt-select w-44" :aria-label="t('common.status')">
                    <option value="">{{ t('orders.filter_status') }}</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ t(`orders.status_${s}`) }}</option>
                </select>
            </div>
        </template>
        <div class="kt-table-wrapper overflow-x-auto">
            <table class="kt-table">
                <thead>
                    <tr>
                        <th>{{ t('orders.number') }}</th>
                        <th>{{ t('admin.customer') }}</th>
                        <th>{{ t('admin.phone') }}</th>
                        <th class="text-center">{{ t('admin.items') }}</th>
                        <th>{{ t('common.status') }}</th>
                        <th class="text-end">{{ t('admin.total') }}</th>
                        <th class="text-end">{{ t('admin.date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in orders.data" :key="o.id">
                        <td><Link :href="route('admin.orders.show', o.id)" class="font-mono text-sm font-medium text-primary" dir="ltr">{{ o.number }}</Link></td>
                        <td>{{ o.customer }} <span class="block text-xs text-muted-foreground">{{ o.city }}</span></td>
                        <td dir="ltr" class="text-sm">{{ o.phone }}</td>
                        <td class="text-center tabular-nums">{{ o.items_count }}</td>
                        <td><OrderStatusBadge :status="o.status" /></td>
                        <td class="text-end font-medium" dir="ltr">{{ o.total }}</td>
                        <td class="text-end text-sm text-muted-foreground" dir="ltr">{{ o.date }}</td>
                    </tr>
                    <tr v-if="!orders.data.length"><td colspan="7" class="py-12 text-center text-muted-foreground">{{ t('common.no_results') }}</td></tr>
                </tbody>
            </table>
        </div>
        <template #footer><KtPagination :links="orders.links" :total="orders.total" :from="orders.from" :to="orders.to" class="w-full" /></template>
    </KtCard>
</template>
