<script setup lang="ts">
import PosOrderRow from '@/Components/Pos/PosOrderRow.vue';
import Pagination from '@/Components/Storefront/Pagination.vue';
import { useTrans } from '@/Composables/useTrans';
import type { PosOrderSummary } from '@/types/pos';
import type { PaginationLink } from '@/types';

defineProps<{ orders: { data: PosOrderSummary[]; links: PaginationLink[] } }>();
const { t } = useTrans();
</script>

<template>
    <div class="mx-auto max-w-4xl p-4 sm:p-6">
        <h1 class="text-2xl font-bold">{{ t('pos.my_orders') }}</h1>
        <div class="mt-5 overflow-hidden rounded-2xl bg-white ring-1 ring-stone-200">
            <ul v-if="orders.data.length" class="divide-y divide-stone-100">
                <li v-for="order in orders.data" :key="order.id"><PosOrderRow :order="order" /></li>
            </ul>
            <p v-else class="p-10 text-center text-stone-500">{{ t('pos.no_orders') }}</p>
        </div>
        <Pagination class="mt-6" :links="orders.links" />
    </div>
</template>
