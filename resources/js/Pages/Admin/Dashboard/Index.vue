<script setup lang="ts">
import { Deferred, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import OrderStatusBadge from '@/Components/Admin/OrderStatusBadge.vue';
import RevenueChart from '@/Components/Dashboard/RevenueChart.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useGsap } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

type Kpi = { value: string | number; change: number | null };
const props = defineProps<{
    kpis: { revenue: Kpi; orders: Kpi; average: Kpi; pending: Kpi } | null;
    chart?: { date: string; label: string; revenue: number; orders: number }[] | null;
    catalog: Record<'published' | 'drafts' | 'without_image' | 'without_english' | 'without_hebrew' | 'without_sku' | 'out_of_stock' | 'on_sale' | 'english_ratio', number>;
    topCategories: { id: number; name: string; count: number }[];
    recentOrders: { id: number; number: string; customer: string; status: string; total: string; date: string }[];
}>();

const page = usePage<SharedProps>();
const { t } = useTrans();
const can = (p: string) => page.props.auth.permissions.includes(p);
const root = ref<HTMLElement | null>(null);

const kpiCards = computed(() =>
    props.kpis
        ? [
              { key: 'revenue', label: 'admin.kpi_revenue', icon: 'dollar', tone: 'bg-primary/10 text-primary', ...props.kpis.revenue },
              { key: 'orders', label: 'admin.kpi_orders', icon: 'handcart', tone: 'bg-green-500/10 text-green-600', ...props.kpis.orders },
              { key: 'average', label: 'admin.kpi_average', icon: 'chart-line-up', tone: 'bg-violet-500/10 text-violet-600', ...props.kpis.average },
              { key: 'pending', label: 'admin.stats_pending', icon: 'time', tone: 'bg-amber-500/10 text-amber-600', ...props.kpis.pending },
          ]
        : [{ key: 'products', label: 'admin.stats_products', icon: 'basket', tone: 'bg-primary/10 text-primary', value: props.catalog.published, change: null }],
);

const health = computed(() => [
    { key: 'without_image', icon: 'picture', bad: true },
    { key: 'without_english', icon: 'geolocation', bad: true },
    { key: 'without_hebrew', icon: 'geolocation', bad: false },
    { key: 'without_sku', icon: 'barcode', bad: true },
    { key: 'out_of_stock', icon: 'cross-circle', bad: true },
    { key: 'on_sale', icon: 'discount', bad: false },
] as const);
const maxCategory = computed(() => Math.max(1, ...props.topCategories.map((c) => c.count)));

useGsap(root, ({ gsap }) => {
    gsap.from('[data-kpi]', { autoAlpha: 0, y: 18, duration: 0.5, stagger: 0.07, ease: 'power2.out' });
    gsap.from('[data-bar]', { scaleX: 0, transformOrigin: document.dir === 'rtl' ? 'right center' : 'left center', duration: 0.9, stagger: 0.06, ease: 'power3.out', delay: 0.2 });
    root.value!.querySelectorAll<HTMLElement>('[data-count]').forEach((el) => {
        const target = Number(el.dataset.count);
        const counter = { v: 0 };
        gsap.to(counter, { v: target, duration: 1.1, ease: 'power2.out', onUpdate: () => (el.textContent = Math.round(counter.v).toLocaleString()) });
    });
});
</script>

<template>
    <div ref="root">
        <PageHeader :title="t('admin.welcome', { name: page.props.auth.user?.name ?? '' })" :description="t('admin.dashboard_subtitle')">
            <template #actions>
                <Link v-if="can('products.create')" :href="route('admin.products.create')" class="kt-btn"><KtIcon name="plus" /> {{ t('admin.new_product') }}</Link>
                <a :href="route('storefront.home')" target="_blank" rel="noopener" class="kt-btn kt-btn-outline"><KtIcon name="shop" /> {{ t('admin.view_store') }}</a>
            </template>
        </PageHeader>

        <!-- KPIs -->
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="card in kpiCards" :key="card.key" data-kpi class="kt-card">
                <div class="kt-card-content flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <span :class="['flex size-11 items-center justify-center rounded-xl text-xl', card.tone]"><KtIcon :name="card.icon" /></span>
                        <span
                            v-if="card.change !== null"
                            :class="['kt-badge kt-badge-sm kt-badge-outline', card.change >= 0 ? 'kt-badge-success' : 'kt-badge-destructive']"
                            dir="ltr"
                            :title="t('admin.vs_last_month')"
                        >
                            {{ card.change >= 0 ? '▲' : '▼' }} {{ Math.abs(card.change) }}%
                        </span>
                    </div>
                    <div>
                        <p class="text-2xl font-semibold leading-none text-mono tabular-nums" dir="ltr">
                            <span v-if="typeof card.value === 'number'" :data-count="card.value">{{ card.value }}</span>
                            <template v-else>{{ card.value }}</template>
                        </p>
                        <p class="mt-2 text-sm text-secondary-foreground">{{ t(card.label) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <!-- Revenue -->
            <KtCard v-if="kpis" :title="t('admin.revenue_30d')" class="lg:col-span-2">
                <Deferred data="chart">
                    <template #fallback><div class="h-72 animate-pulse rounded-lg bg-accent" /></template>
                    <RevenueChart v-if="chart" :points="chart" currency="₪" />
                </Deferred>
            </KtCard>

            <!-- Catalog health -->
            <KtCard :title="t('admin.catalog_health')" :class="kpis ? '' : 'lg:col-span-2'">
                <div class="mb-5">
                    <div class="mb-2 flex justify-between text-sm">
                        <span class="text-secondary-foreground">{{ t('admin.english_coverage') }}</span>
                        <span class="font-semibold text-mono tabular-nums">{{ catalog.english_ratio }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-accent">
                        <div data-bar class="h-full rounded-full bg-primary" :style="{ width: `${catalog.english_ratio}%` }" />
                    </div>
                </div>
                <ul class="divide-y divide-border">
                    <li v-for="item in health" :key="item.key" class="flex items-center justify-between gap-3 py-2.5 text-sm">
                        <span class="flex items-center gap-2.5 text-foreground">
                            <KtIcon :name="item.icon" class="text-base text-muted-foreground" />
                            {{ t(`admin.health_${item.key}`) }}
                        </span>
                        <span :class="['kt-badge kt-badge-sm', item.bad && catalog[item.key] > 0 ? 'kt-badge-warning kt-badge-outline' : 'kt-badge-secondary']" class="tabular-nums">
                            {{ catalog[item.key] }}
                        </span>
                    </li>
                </ul>
            </KtCard>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <!-- Recent orders -->
            <KtCard v-if="kpis" :title="t('admin.recent_orders')" flush class="lg:col-span-2">
                <template #toolbar>
                    <Link :href="route('admin.orders.index')" class="kt-btn kt-btn-sm kt-btn-outline">{{ t('admin.orders') }}</Link>
                </template>
                <div v-if="recentOrders.length" class="kt-table-wrapper overflow-x-auto">
                    <table class="kt-table">
                        <thead>
                            <tr>
                                <th>{{ t('orders.number') }}</th>
                                <th>{{ t('admin.customer') }}</th>
                                <th>{{ t('common.status') }}</th>
                                <th class="text-end">{{ t('admin.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in recentOrders" :key="order.id">
                                <td>
                                    <Link :href="route('admin.orders.show', order.id)" class="font-mono text-sm font-medium text-primary" dir="ltr">{{ order.number }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ order.date }}</p>
                                </td>
                                <td>{{ order.customer }}</td>
                                <td><OrderStatusBadge :status="order.status" /></td>
                                <td class="text-end font-medium" dir="ltr">{{ order.total }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="flex flex-col items-center gap-3 px-5 py-14 text-center">
                    <span class="flex size-14 items-center justify-center rounded-full bg-accent text-2xl text-muted-foreground"><KtIcon name="handcart" /></span>
                    <p class="text-sm text-secondary-foreground">{{ t('admin.no_orders_yet') }}</p>
                </div>
            </KtCard>

            <!-- Top categories -->
            <KtCard :title="t('admin.top_categories')">
                <ul class="space-y-4">
                    <li v-for="category in topCategories" :key="category.id">
                        <div class="mb-1.5 flex justify-between gap-3 text-sm">
                            <span class="truncate text-foreground">{{ category.name }}</span>
                            <span class="font-medium text-mono tabular-nums">{{ category.count }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-accent">
                            <div data-bar class="h-full rounded-full bg-primary/80" :style="{ width: `${(category.count / maxCategory) * 100}%` }" />
                        </div>
                    </li>
                </ul>
            </KtCard>
        </div>
    </div>
</template>
