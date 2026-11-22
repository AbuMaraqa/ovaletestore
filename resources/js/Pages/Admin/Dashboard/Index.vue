<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import OrderStatusBadge from '@/Components/Admin/OrderStatusBadge.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

defineProps<{
    stats: { products: number; orders_today: number | null; revenue_month: string | null; pending: number | null };
    recentOrders: { id: number; number: string; customer: string; status: string; total: string; date: string }[];
}>();
const { t } = useTrans();
</script>

<template>
    <PageHeader :title="t('admin.dashboard')" />

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="card in [
            { label: 'admin.stats_products', value: stats.products, icon: 'basket', tone: 'bg-primary/10 text-primary' },
            { label: 'admin.stats_orders_today', value: stats.orders_today, icon: 'handcart', tone: 'bg-green-500/10 text-green-600' },
            { label: 'admin.stats_revenue_month', value: stats.revenue_month, icon: 'dollar', tone: 'bg-violet-500/10 text-violet-600' },
            { label: 'admin.stats_pending', value: stats.pending, icon: 'time', tone: 'bg-amber-500/10 text-amber-600' },
        ].filter((c) => c.value !== null)" :key="card.label" class="kt-card">
            <div class="kt-card-content flex items-center gap-4">
                <span :class="['flex size-12 items-center justify-center rounded-xl text-2xl', card.tone]"><KtIcon :name="card.icon" /></span>
                <div>
                    <p class="text-sm text-muted-foreground">{{ t(card.label) }}</p>
                    <p class="mt-1 text-2xl font-semibold text-mono tabular-nums" dir="ltr">{{ card.value }}</p>
                </div>
            </div>
        </div>
    </div>

    <KtCard v-if="recentOrders.length" :title="t('admin.recent_orders')" flush class="mt-6">
        <template #toolbar>
            <Link :href="route('admin.orders.index')" class="kt-btn kt-btn-sm kt-btn-ghost">{{ t('admin.orders') }}</Link>
        </template>
        <div class="kt-table-wrapper overflow-x-auto">
            <table class="kt-table">
                <thead>
                    <tr>
                        <th>{{ t('orders.number') }}</th>
                        <th>{{ t('admin.customer') }}</th>
                        <th>{{ t('common.status') }}</th>
                        <th class="text-end">{{ t('admin.total') }}</th>
                        <th class="text-end">{{ t('admin.date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="order in recentOrders" :key="order.id">
                        <td><Link :href="route('admin.orders.show', order.id)" class="font-mono text-sm font-medium text-primary" dir="ltr">{{ order.number }}</Link></td>
                        <td>{{ order.customer }}</td>
                        <td><OrderStatusBadge :status="order.status" /></td>
                        <td class="text-end font-medium" dir="ltr">{{ order.total }}</td>
                        <td class="text-end text-muted-foreground">{{ order.date }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </KtCard>
</template>
