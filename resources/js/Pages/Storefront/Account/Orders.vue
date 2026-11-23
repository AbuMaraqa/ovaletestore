<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AccountShell from '@/Components/Storefront/AccountShell.vue';
import OrderStatusPill from '@/Components/Storefront/OrderStatusPill.vue';
import Pagination from '@/Components/Storefront/Pagination.vue';
import { useTrans } from '@/Composables/useTrans';
import type { PaginationLink } from '@/types';

defineProps<{ orders: { data: { number: string; status: string; total: string; items_count: number; date: string; url: string }[]; links: PaginationLink[] } }>();
const { t } = useTrans();
</script>

<template>
    <AccountShell :title="t('account.my_orders')">
        <div class="overflow-hidden rounded-3xl bg-white ring-1 ring-stone-200/70">
            <table v-if="orders.data.length" class="w-full text-sm">
                <thead class="bg-stone-50 text-start text-xs uppercase tracking-wide text-stone-500">
                    <tr>
                        <th class="px-5 py-3 text-start">{{ t('orders.number') }}</th>
                        <th class="px-5 py-3 text-start">{{ t('account.date') }}</th>
                        <th class="px-5 py-3 text-start">{{ t('common.status') }}</th>
                        <th class="px-5 py-3 text-end">{{ t('cart.total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="o in orders.data" :key="o.number" class="hover:bg-stone-50">
                        <td class="px-5 py-4"><Link :href="o.url" class="font-mono font-bold text-brand-700 hover:underline" dir="ltr">{{ o.number }}</Link></td>
                        <td class="px-5 py-4 text-stone-500">{{ o.date }}</td>
                        <td class="px-5 py-4"><OrderStatusPill :status="o.status" /></td>
                        <td class="px-5 py-4 text-end font-bold" dir="ltr">{{ o.total }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="py-16 text-center text-stone-500">{{ t('account.no_orders') }}</p>
        </div>
        <Pagination :links="orders.links" class="mt-8" />
    </AccountShell>
</template>
