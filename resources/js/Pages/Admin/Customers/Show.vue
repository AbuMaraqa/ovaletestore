<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TraderReviewModal from '@/Components/Admin/Traders/TraderReviewModal.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import OrderStatusBadge from '@/Components/Admin/OrderStatusBadge.vue';
import RowActions from '@/Components/DataTable/RowActions.vue';
import TablePagination from '@/Components/DataTable/TablePagination.vue';
import type { TablePage } from '@/Components/DataTable/types';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { pageMeta } from '@/Composables/useDataTable';
import { useTrans } from '@/Composables/useTrans';
import type { Avatar } from '@/types';

const props = defineProps<{
    customer: {
        id: number; name: string; email: string; phone: string | null; avatar: Avatar; is_active: boolean;
        from_woocommerce: boolean; registered: string | null; email_verified: boolean; edit_url: string | null; delete_url: string | null;
        trader: { status: 'pending' | 'approved' | 'rejected' | null; business_name: string | null; tier_id: number | null; tier: string | null; requested: string | null; can_review: boolean };
    };
    tiers: { value: number; label: string }[];
    stats: { orders: number; spent: string; average: string; last_order: string | null };
    orders: TablePage<{ id: number; number: string; status: string; items_count: number; total: string; date: string; show_url: string }>;
}>();

const { t } = useTrans();
const reviewing = ref(false);
const meta = computed(() => pageMeta(props.orders));
const tiles = computed(() => [
    { label: t('customers.orders'), value: String(props.stats.orders), icon: 'handcart' },
    { label: t('customers.spent'), value: props.stats.spent, icon: 'dollar' },
    { label: t('customers.average'), value: props.stats.average, icon: 'chart-simple' },
    { label: t('customers.last_order'), value: props.stats.last_order ?? '—', icon: 'calendar-tick' },
]);
</script>

<template>
    <PageHeader :title="customer.name" :description="t('customers.profile')">
        <template #actions>
            <KtButton :href="route('admin.customers.index')" variant="outline">{{ t('common.back') }}</KtButton>
            <RowActions :edit-url="customer.edit_url" :delete-url="customer.delete_url" :label="customer.name" :activity="{ type: 'user', id: customer.id }" />
        </template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-12">
        <aside class="xl:col-span-4">
            <KtCard>
                <div class="flex flex-col items-center gap-3 border-b border-border pb-5 text-center">
                    <UserAvatar :avatar="customer.avatar" :name="customer.name" size="size-20 text-2xl" :ring="false" />
                    <div>
                        <h2 class="text-lg font-semibold text-mono">{{ customer.name }}</h2>
                        <div class="mt-1.5 flex flex-wrap justify-center gap-1.5">
                            <span :class="['kt-badge kt-badge-sm', customer.is_active ? 'kt-badge-success kt-badge-outline' : 'kt-badge-outline']">{{ customer.is_active ? t('admin.active') : t('admin.inactive') }}</span>
                            <span class="kt-badge kt-badge-sm kt-badge-outline">{{ customer.from_woocommerce ? t('customers.from_woocommerce') : t('customers.registered_here') }}</span>
                        </div>
                    </div>
                </div>
                <dl class="flex flex-col gap-3.5 pt-5 text-sm">
                    <div class="flex items-center gap-3">
                        <KtIcon name="sms" class="text-lg text-muted-foreground" />
                        <a :href="`mailto:${customer.email}`" class="text-mono hover:text-primary" dir="ltr">{{ customer.email }}</a>
                        <KtIcon v-if="customer.email_verified" name="verify" class="text-green-600" :title="t('customers.email_verified')" />
                    </div>
                    <div v-if="customer.phone" class="flex items-center gap-3">
                        <KtIcon name="phone" class="text-lg text-muted-foreground" />
                        <a :href="`tel:${customer.phone}`" class="text-mono hover:text-primary" dir="ltr">{{ customer.phone }}</a>
                    </div>
                    <div class="flex items-center gap-3">
                        <KtIcon name="calendar" class="text-lg text-muted-foreground" />
                        <span>{{ t('customers.registered') }}: {{ customer.registered }}</span>
                    </div>
                </dl>
                <p v-if="customer.from_woocommerce" class="mt-5 rounded-lg bg-muted p-3 text-xs text-secondary-foreground">{{ t('customers.woo_password_note') }}</p>
            </KtCard>

            <KtCard :title="t('traders.trader_account')" class="mt-6">
                <div v-if="customer.trader.status" class="space-y-2 text-sm">
                    <p class="flex items-center justify-between">
                        <span class="text-secondary-foreground">{{ t('common.status') }}</span>
                        <span :class="['kt-badge kt-badge-sm kt-badge-outline', { pending: 'kt-badge-warning', approved: 'kt-badge-success', rejected: 'kt-badge-destructive' }[customer.trader.status]]">{{ t(`traders.status_${customer.trader.status}`) }}</span>
                    </p>
                    <p v-if="customer.trader.business_name" class="flex justify-between gap-3"><span class="text-secondary-foreground">{{ t('traders.business_name') }}</span><span class="font-medium text-mono">{{ customer.trader.business_name }}</span></p>
                    <p v-if="customer.trader.tier" class="flex justify-between gap-3"><span class="text-secondary-foreground">{{ t('traders.tier') }}</span><span class="font-medium text-mono">{{ customer.trader.tier }}</span></p>
                    <p v-if="customer.trader.requested" class="flex justify-between gap-3"><span class="text-secondary-foreground">{{ t('traders.requested') }}</span><span>{{ customer.trader.requested }}</span></p>
                </div>
                <p v-else class="text-sm text-secondary-foreground">{{ t('traders.not_trader') }}</p>
                <button v-if="customer.trader.can_review" type="button" class="kt-btn kt-btn-sm kt-btn-outline mt-4 w-full justify-center" @click="reviewing = true">
                    {{ customer.trader.status === 'pending' ? t('traders.review') : customer.trader.status ? t('traders.change_tier') : t('traders.make_trader') }}
                </button>
            </KtCard>
        </aside>

        <div class="space-y-6 xl:col-span-8">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div v-for="tile in tiles" :key="tile.label" class="kt-card gap-2 p-4">
                    <KtIcon :name="tile.icon" class="text-xl text-primary" />
                    <p class="text-lg font-semibold tabular-nums text-mono" dir="auto">{{ tile.value }}</p>
                    <p class="text-xs text-secondary-foreground">{{ tile.label }}</p>
                </div>
            </div>

            <KtCard :title="t('customers.orders')" flush>
                <div class="kt-table-wrapper overflow-x-auto">
                    <table class="kt-table kt-table-border text-sm">
                        <thead>
                            <tr>
                                <th>{{ t('orders.number') }}</th>
                                <th>{{ t('admin.date') }}</th>
                                <th class="text-center">{{ t('admin.items') }}</th>
                                <th>{{ t('common.status') }}</th>
                                <th class="text-end">{{ t('admin.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in orders.data" :key="order.id">
                                <td><Link :href="order.show_url" class="font-medium text-primary hover:underline" dir="ltr">{{ order.number }}</Link></td>
                                <td class="whitespace-nowrap text-secondary-foreground">{{ order.date }}</td>
                                <td class="text-center tabular-nums">{{ order.items_count }}</td>
                                <td><OrderStatusBadge :status="order.status" /></td>
                                <td class="whitespace-nowrap text-end font-medium tabular-nums" dir="ltr">{{ order.total }}</td>
                            </tr>
                            <tr v-if="!orders.data.length">
                                <td colspan="5" class="py-12 text-center text-muted-foreground">{{ t('customers.no_orders') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <template v-if="meta.last_page > 1" #footer>
                    <TablePagination :meta="meta" :per-page="15" :options="[15]" />
                </template>
            </KtCard>
        </div>
    </div>

    <TraderReviewModal
        :trader="reviewing ? { id: customer.id, name: customer.name, business_name: customer.trader.business_name, status: customer.trader.status, tier_id: customer.trader.tier_id } : null"
        :tiers="tiers"
        @close="reviewing = false"
    />
</template>
