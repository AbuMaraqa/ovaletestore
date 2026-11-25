<script setup lang="ts">
import { computed, inject, type Ref } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';
import type { PriceTierOption, VariantForm } from '@/types/admin';

/**
 * Trader prices of one variant, one field per price tier. Empty = the tier's automatic % off the
 * regular price (shown as the placeholder). Tiers come from the product form (provide/inject).
 */
const model = defineModel<VariantForm>({ required: true });
const props = defineProps<{ errors: Record<string, string>; prefix: string }>();
const tiers = inject<Ref<PriceTierOption[]>>('priceTiers');
const { t } = useTrans();

const list = computed(() => tiers?.value ?? []);
const prices = computed(() => (model.value.tier_prices ??= {}));
const automatic = (tier: PriceTierOption) => {
    const regular = Number(model.value.regular_price);
    return regular > 0 ? (regular * (1 - tier.discount_percent / 100)).toFixed(2) : '';
};
const id = (tierId: number) => `${props.prefix.replace('.', '-')}-tier-${tierId}`;
</script>

<template>
    <fieldset v-if="list.length" class="mt-5 rounded-xl border border-dashed border-border p-4">
        <legend class="flex items-center gap-2 px-1 text-sm font-semibold text-mono"><KtIcon name="shop" class="text-primary" />{{ t('traders.trader_prices') }}</legend>
        <p class="mb-3 text-xs text-secondary-foreground">{{ t('traders.trader_prices_help') }}</p>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="tier in list" :key="tier.id" class="flex flex-col gap-1.5">
                <label :for="id(tier.id)" class="text-sm text-mono">
                    {{ tier.name }}
                    <span v-if="tier.discount_percent" class="text-xs text-muted-foreground">(−{{ tier.discount_percent }}%)</span>
                    <span v-if="!tier.is_active" class="kt-badge kt-badge-sm kt-badge-outline ms-1">{{ t('admin.inactive') }}</span>
                </label>
                <input
                    :id="id(tier.id)"
                    v-model="prices[tier.id]"
                    type="number"
                    step="0.01"
                    min="0"
                    class="kt-input"
                    dir="ltr"
                    :placeholder="automatic(tier)"
                    :aria-describedby="`${id(tier.id)}-hint`"
                />
                <span :id="`${id(tier.id)}-hint`" class="sr-only">{{ t('traders.empty_uses_automatic') }}</span>
                <p v-if="errors[`${prefix}.tier_prices.${tier.id}`]" class="text-xs text-destructive">{{ errors[`${prefix}.tier_prices.${tier.id}`] }}</p>
            </div>
        </div>
    </fieldset>
</template>
