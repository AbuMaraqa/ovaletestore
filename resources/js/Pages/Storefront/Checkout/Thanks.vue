<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Storefront/Icon.vue';
import { useTrans } from '@/Composables/useTrans';

defineProps<{ order: { number: string; total: string; phone: string } }>();
const { t } = useTrans();
</script>

<template>
    <div class="container-store py-16 lg:py-24">
        <div class="mx-auto max-w-lg rounded-[2rem] bg-white p-8 text-center ring-1 ring-stone-100 sm:p-12">
            <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                <Icon name="check" class="size-8" />
            </span>
            <h1 class="mt-6 text-2xl font-bold text-cocoa">{{ t('cart.thank_you') }}</h1>
            <dl class="mt-6 grid grid-cols-2 gap-4 rounded-2xl bg-stone-50 p-4 text-sm">
                <div>
                    <dt class="text-stone-500">{{ t('cart.order_number') }}</dt>
                    <dd class="mt-1 font-mono font-semibold" dir="ltr">{{ order.number }}</dd>
                </div>
                <div>
                    <dt class="text-stone-500">{{ t('cart.total') }}</dt>
                    <dd class="mt-1 font-semibold" dir="ltr">{{ order.total }}</dd>
                </div>
            </dl>
            <p class="mt-6 text-sm text-stone-600">{{ t('cart.we_will_contact', { phone: `\u2066${order.phone}\u2069` }) }}</p>
            <Link :href="route('storefront.shop')" class="btn-primary mt-8">{{ t('cart.continue_shopping') }}</Link>
        </div>
    </div>
</template>
