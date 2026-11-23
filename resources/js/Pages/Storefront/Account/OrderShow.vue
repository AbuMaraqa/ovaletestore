<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AccountShell from '@/Components/Storefront/AccountShell.vue';
import OrderStatusPill from '@/Components/Storefront/OrderStatusPill.vue';
import { useTrans } from '@/Composables/useTrans';

defineProps<{
    order: {
        number: string; status: string; total: string; date: string; subtotal: string; shipping_total: string;
        shipping_city: string | null; shipping_address: string | null; shipping_method: string | null; customer_note: string | null;
        items: { id: number; name: string; variant_label: string | null; quantity: number; unit_price: string; line_total: string }[];
    };
}>();
const { t } = useTrans();
</script>

<template>
    <AccountShell :title="t('account.order', { number: order.number })">
        <div class="mb-5 flex flex-wrap items-center gap-3 text-sm text-stone-500">
            <OrderStatusPill :status="order.status" /> <span>{{ order.date }}</span>
            <Link :href="route('account.orders')" class="ms-auto font-semibold text-brand-700 hover:underline">{{ t('common.back') }}</Link>
        </div>
        <div class="grid gap-6 lg:grid-cols-3">
            <ul class="divide-y divide-stone-100 rounded-3xl bg-white px-5 ring-1 ring-stone-200/70 lg:col-span-2">
                <li v-for="item in order.items" :key="item.id" class="flex items-center justify-between gap-4 py-4 text-sm">
                    <div>
                        <p class="font-semibold text-cocoa">{{ item.name }}</p>
                        <p class="text-xs text-stone-500">{{ item.variant_label }} · {{ item.quantity }} × <span dir="ltr">{{ item.unit_price }}</span></p>
                    </div>
                    <b dir="ltr">{{ item.line_total }}</b>
                </li>
            </ul>
            <aside class="space-y-4">
                <dl class="space-y-2 rounded-3xl bg-white p-5 text-sm ring-1 ring-stone-200/70">
                    <div class="flex justify-between"><dt class="text-stone-500">{{ t('cart.subtotal') }}</dt><dd dir="ltr">{{ order.subtotal }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">{{ t('cart.shipping') }}</dt><dd dir="ltr">{{ order.shipping_total }}</dd></div>
                    <div class="flex justify-between border-t border-stone-100 pt-2 text-base font-bold text-cocoa"><dt>{{ t('cart.total') }}</dt><dd dir="ltr">{{ order.total }}</dd></div>
                </dl>
                <div class="rounded-3xl bg-white p-5 text-sm ring-1 ring-stone-200/70">
                    <p class="font-bold text-cocoa">{{ t('cart.delivery_details') }}</p>
                    <p v-if="order.shipping_method" class="mt-2 font-semibold text-cocoa">{{ order.shipping_method }}</p>
                    <p v-if="order.shipping_city || order.shipping_address" class="mt-1 text-stone-600">{{ [order.shipping_city, order.shipping_address].filter(Boolean).join(' — ') }}</p>
                    <p v-if="order.customer_note" class="mt-2 whitespace-pre-line text-stone-500">{{ order.customer_note }}</p>
                </div>
            </aside>
        </div>
    </AccountShell>
</template>
