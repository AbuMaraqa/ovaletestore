<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import OrderStatusPill from '@/Components/Storefront/OrderStatusPill.vue';
import { useTrans } from '@/Composables/useTrans';
import type { PosOrderSummary } from '@/types/pos';

type Line = { name: string; variant: string | null; sku: string | null; quantity: number; unit_price: string; line_total: string };
defineProps<{ order: PosOrderSummary & { note: string | null; items: Line[] } }>();
const { t } = useTrans();
const print = () => window.print();
</script>

<template>
    <div class="mx-auto max-w-3xl p-4 sm:p-6">
        <div class="flex flex-wrap gap-3 print:hidden">
            <Link :href="route('pos.index')" class="btn-accent h-12">{{ t('pos.new_order') }}</Link>
            <Link :href="route('pos.orders')" class="btn-ghost h-12">{{ t('pos.my_orders') }}</Link>
            <button type="button" class="btn-ghost ms-auto h-12" @click="print">{{ t('pos.print') }}</button>
        </div>

        <article class="mt-5 rounded-2xl bg-white p-6 ring-1 ring-stone-200 print:mt-0 print:ring-0">
            <header class="flex flex-wrap items-start justify-between gap-4 border-b border-stone-100 pb-5">
                <div>
                    <p class="text-sm text-stone-500">{{ t('pos.company') }}</p>
                    <h1 class="text-2xl font-bold">{{ order.company }}</h1>
                </div>
                <div class="text-end">
                    <p class="font-bold" dir="ltr">{{ order.number }}</p>
                    <p class="text-sm text-stone-500" dir="ltr">{{ order.date }}</p>
                    <OrderStatusPill class="mt-2" :status="order.status" />
                </div>
            </header>

            <table class="mt-4 w-full text-sm">
                <thead class="text-stone-500">
                    <tr>
                        <th class="py-2 text-start font-medium">{{ t('pos.items') }}</th>
                        <th class="py-2 text-center font-medium">{{ t('pos.quantity') }}</th>
                        <th class="py-2 text-end font-medium">{{ t('pos.unit_price') }}</th>
                        <th class="py-2 text-end font-medium">{{ t('pos.line_total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="(line, i) in order.items" :key="i">
                        <td class="py-2.5">
                            <span class="font-medium">{{ line.name }}</span>
                            <span v-if="line.variant" class="text-stone-500"> · {{ line.variant }}</span>
                            <span v-if="line.sku" class="block text-xs text-stone-400" dir="ltr">{{ line.sku }}</span>
                        </td>
                        <td class="py-2.5 text-center tabular-nums">{{ line.quantity }}</td>
                        <td class="py-2.5 text-end tabular-nums" dir="ltr">{{ line.unit_price }}</td>
                        <td class="py-2.5 text-end font-semibold tabular-nums" dir="ltr">{{ line.line_total }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-4 flex items-baseline justify-between border-t border-stone-200 pt-4">
                <span class="text-stone-500">{{ t('pos.total') }} <span class="text-xs">({{ t('pos.on_account') }})</span></span>
                <span class="text-2xl font-bold tabular-nums" dir="ltr">{{ order.total }}</span>
            </div>
            <p v-if="order.note" class="mt-4 rounded-xl bg-stone-50 p-4 text-sm"><strong>{{ t('pos.note') }}:</strong> {{ order.note }}</p>
        </article>
    </div>
</template>
