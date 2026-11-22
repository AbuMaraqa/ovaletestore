<script setup lang="ts">
import { useTrans } from '@/Composables/useTrans';
import type { CartPayload } from '@/types/cart';

defineProps<{ cart: CartPayload }>();
const { t } = useTrans();
</script>

<template>
    <div class="rounded-[2rem] bg-white p-6 ring-1 ring-stone-100">
        <h2 class="text-lg font-semibold text-cocoa">{{ t('cart.order_summary') }}</h2>
        <dl class="mt-5 space-y-3 text-sm">
            <div class="flex justify-between">
                <dt class="text-stone-500">{{ t('cart.subtotal') }}</dt>
                <dd class="font-medium" dir="ltr">{{ cart.subtotal }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-stone-500">{{ t('cart.shipping') }}</dt>
                <dd class="font-medium" dir="ltr">{{ cart.shipping_total ?? t('cart.free') }}</dd>
            </div>
            <div class="flex justify-between border-t border-stone-200 pt-4 text-base">
                <dt class="font-semibold text-cocoa">{{ t('cart.total') }}</dt>
                <dd class="font-bold text-cocoa" dir="ltr">{{ cart.total }}</dd>
            </div>
        </dl>
        <slot />
    </div>
</template>
