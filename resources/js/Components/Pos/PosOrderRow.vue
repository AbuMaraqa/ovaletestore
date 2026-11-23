<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import OrderStatusPill from '@/Components/Storefront/OrderStatusPill.vue';
import { useTrans } from '@/Composables/useTrans';
import type { PosOrderSummary } from '@/types/pos';

defineProps<{ order: PosOrderSummary; compact?: boolean }>();
const { t } = useTrans();
</script>

<template>
    <Link :href="route('pos.orders.show', order.id)" class="flex items-center gap-3 py-3 hover:bg-stone-50" :class="compact ? '' : 'px-4'">
        <span class="min-w-0 flex-1">
            <span class="block truncate font-semibold">{{ order.company }}</span>
            <span class="mt-0.5 block text-xs text-stone-500" dir="ltr">{{ order.number }} · {{ order.date }}</span>
        </span>
        <span v-if="!compact" class="hidden text-sm text-stone-500 sm:block">{{ t('pos.items_count', { count: order.items_count }) }}</span>
        <OrderStatusPill :status="order.status" />
        <span class="w-24 shrink-0 text-end font-bold tabular-nums" dir="ltr">{{ order.total }}</span>
    </Link>
</template>
