<script setup lang="ts">
import Icon from '@/Components/Storefront/Icon.vue';
import { useTrans } from '@/Composables/useTrans';
import type { CartPayload } from '@/types/cart';

defineProps<{ cart: CartPayload }>();
const { t } = useTrans();
</script>

<template>
    <div class="rounded-[2rem] bg-white p-6 ring-1 ring-stone-100">
        <h2 class="text-lg font-bold text-cocoa">{{ t('cart.order_summary') }}</h2>

        <!-- Free-shipping nudge: the clearest "add a little more" message in the funnel. -->
        <div v-if="cart.free_shipping" class="mt-4 rounded-2xl bg-brand-50 p-4">
            <p class="flex items-center gap-2 text-sm font-semibold text-brand-900">
                <Icon name="truck" class="size-5 shrink-0" />
                <span v-if="cart.free_shipping.remaining">{{ t('cart.free_shipping_remaining', { amount: cart.free_shipping.remaining }) }}</span>
                <span v-else>{{ t('cart.free_shipping_unlocked') }}</span>
            </p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-white" role="progressbar" :aria-valuenow="cart.free_shipping.progress" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-brand-600 transition-[width] duration-500" :style="{ width: `${cart.free_shipping.progress}%` }" />
            </div>
        </div>

        <dl class="mt-5 space-y-3 text-[15px]">
            <div class="flex justify-between">
                <dt class="text-stone-600">{{ t('cart.subtotal') }}</dt>
                <dd class="font-medium" dir="ltr">{{ cart.subtotal }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-stone-600">{{ t('cart.shipping') }}</dt>
                <dd v-if="cart.shipping_selected" class="font-medium" :class="!cart.shipping_total && 'text-emerald-700'" dir="ltr">{{ cart.shipping_total ?? t('cart.free') }}</dd>
                <dd v-else class="text-sm text-stone-500">{{ t('cart.shipping_at_checkout') }}</dd>
            </div>
            <div class="flex justify-between border-t border-stone-200 pt-4 text-base">
                <dt class="font-bold text-cocoa">{{ cart.shipping_selected ? t('cart.total') : t('cart.total_before_shipping') }}</dt>
                <dd class="text-xl font-extrabold text-cocoa" dir="ltr">{{ cart.total }}</dd>
            </div>
        </dl>
        <slot />
    </div>
</template>
