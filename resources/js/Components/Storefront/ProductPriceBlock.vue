<script setup lang="ts">
import { computed } from 'vue';
import { useCountdown } from '@/Composables/useCountdown';
import { useTrans } from '@/Composables/useTrans';

/** Price, "-X%" badge, "you save" line, real sale countdown and stock pill of the selected variant. */
const props = defineProps<{
    variant: {
        price: string;
        regular_price: string | null;
        on_sale: boolean;
        discount_percent: number | null;
        saving: string | null;
        sale_ends_at: string | null;
        purchasable: boolean;
        stock_status: 'in_stock' | 'out_of_stock' | 'on_backorder';
    } | null;
}>();

const { t } = useTrans();
const { remaining } = useCountdown(computed(() => props.variant?.sale_ends_at));
const pad = (n: number) => String(n).padStart(2, '0');

const stock = computed(() => {
    if (!props.variant) return null;
    const label = { in_stock: t('storefront.in_stock'), out_of_stock: t('storefront.out_of_stock'), on_backorder: t('storefront.on_backorder') }[props.variant.stock_status];
    return { label, ok: props.variant.purchasable };
});
</script>

<template>
    <div class="mt-5" aria-live="polite">
        <template v-if="variant">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <span class="text-3xl font-extrabold sm:text-4xl" :class="variant.on_sale ? 'text-brand-700' : 'text-cocoa'" dir="ltr">{{ variant.price }}</span>
                <span v-if="variant.regular_price" class="text-lg text-stone-500 line-through" dir="ltr">{{ variant.regular_price }}</span>
                <span v-if="variant.on_sale" class="rounded-full bg-brand-600 px-3 py-1 text-sm font-bold text-white" dir="ltr">
                    {{ variant.discount_percent ? t('storefront.discount_badge', { percent: variant.discount_percent }) : t('storefront.sale') }}
                </span>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                <span v-if="stock" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-semibold" :class="stock.ok ? 'bg-emerald-50 text-emerald-800' : 'bg-stone-100 text-stone-600'">
                    <span class="size-2 rounded-full" :class="stock.ok ? 'bg-emerald-500' : 'bg-stone-400'" aria-hidden="true" />
                    {{ stock.label }}
                </span>
                <span v-if="variant.saving" class="rounded-full bg-brand-50 px-3 py-1 font-semibold text-brand-800">
                    {{ t('storefront.you_save', { amount: variant.saving }) }}
                </span>
            </div>

            <p v-if="remaining" class="mt-3 inline-flex flex-wrap items-center gap-2 rounded-xl bg-cocoa px-4 py-2.5 text-sm font-semibold text-white">
                <span>{{ t('storefront.offer_ends_in') }}</span>
                <span class="font-mono text-base tabular-nums" dir="ltr">
                    <template v-if="remaining.days">{{ remaining.days }}{{ t('storefront.days_short') }} </template>{{ pad(remaining.hours) }}:{{ pad(remaining.minutes) }}:{{ pad(remaining.seconds) }}
                </span>
            </p>
        </template>
        <span v-else class="text-base text-stone-600">{{ t('storefront.unavailable_combination') }}</span>
    </div>
</template>
