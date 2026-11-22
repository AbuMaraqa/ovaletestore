<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import ResponsiveImage from '@/Components/Common/ResponsiveImage.vue';
import Icon from '@/Components/Storefront/Icon.vue';
import OrderSummary from '@/Components/Storefront/OrderSummary.vue';
import QuantityInput from '@/Components/Storefront/QuantityInput.vue';
import { useTrans } from '@/Composables/useTrans';
import type { CartPayload } from '@/types/cart';

defineProps<{ cart: CartPayload | null }>();
const { t } = useTrans();

function updateQuantity(itemId: number, quantity: number): void {
    router.patch(route('storefront.cart.update', itemId), { quantity }, { preserveScroll: true, only: ['cart', 'cart_count', 'flash', 'errors'] });
}

function remove(itemId: number): void {
    router.delete(route('storefront.cart.destroy', itemId), { preserveScroll: true, only: ['cart', 'cart_count', 'flash'] });
}
</script>

<template>
    <div class="container-store py-10 lg:py-14">
        <h1 class="text-3xl font-bold tracking-tight text-cocoa">{{ t('cart.title') }}</h1>

        <div v-if="!cart || cart.items.length === 0" class="mt-10 rounded-[2rem] bg-white px-6 py-20 text-center ring-1 ring-stone-100">
            <Icon name="cart" class="mx-auto size-12 text-stone-300" />
            <p class="mt-4 text-stone-600">{{ t('cart.empty') }}</p>
            <Link :href="route('storefront.shop')" class="btn-primary mt-8">{{ t('cart.continue_shopping') }}</Link>
        </div>

        <div v-else class="mt-8 grid gap-8 lg:grid-cols-12">
            <ul class="divide-y divide-stone-200 rounded-[2rem] bg-white px-4 ring-1 ring-stone-100 sm:px-6 lg:col-span-8" role="list">
                <li v-for="item in cart.items" :key="item.id" class="flex gap-4 py-5">
                    <Link :href="item.url" class="size-24 shrink-0 overflow-hidden rounded-2xl bg-stone-50 sm:size-28">
                        <ResponsiveImage :image="item.image" :alt="item.name" sizes="112px" img-class="size-full object-contain p-2" />
                    </Link>
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <Link :href="item.url" class="line-clamp-2 text-sm font-medium text-cocoa hover:underline">{{ item.name }}</Link>
                                <p v-if="item.variant_label" class="mt-1 text-xs text-stone-500">{{ item.variant_label }}</p>
                                <p v-if="!item.available" class="mt-1 text-xs font-medium text-red-600">{{ t('cart.unavailable') }}</p>
                            </div>
                            <p class="shrink-0 text-sm font-semibold" dir="ltr">{{ item.line_total }}</p>
                        </div>
                        <div class="mt-auto flex items-center justify-between gap-3">
                            <QuantityInput :model-value="item.quantity" :max="item.max_quantity ?? 999" @commit="(q) => updateQuantity(item.id, q)" />
                            <button type="button" class="inline-flex min-h-11 items-center gap-1.5 rounded-full px-3 text-sm text-stone-500 hover:bg-stone-50 hover:text-red-600" @click="remove(item.id)">
                                <Icon name="trash" class="size-4" />
                                {{ t('cart.remove') }}
                            </button>
                        </div>
                    </div>
                </li>
            </ul>

            <aside class="lg:col-span-4">
                <OrderSummary :cart="cart" class="lg:sticky lg:top-32">
                    <Link
                        :href="route('storefront.checkout')"
                        :class="['btn-accent mt-6 h-12 w-full text-base', !cart.meets_minimum || cart.items.some((i) => !i.available) ? 'pointer-events-none opacity-50' : '']"
                    >
                        {{ t('cart.checkout') }}
                    </Link>
                    <p v-if="!cart.meets_minimum" class="mt-3 text-center text-xs text-red-600">{{ t('cart.below_minimum') }}</p>
                </OrderSummary>
            </aside>
        </div>
    </div>
</template>
