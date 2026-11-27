<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TierPriceFields from '@/Components/Admin/Products/TierPriceFields.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import SwitchRow from '@/Components/Forms/SwitchRow.vue';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';
import type { VariantForm } from '@/types/admin';

/**
 * Price, inventory and shipping of one purchasable variant (a simple product's only variant, or one row of a
 * variable product when `compact`). Errors come from the page form by key (`variants.0.regular_price`…).
 */
const model = defineModel<VariantForm>({ required: true });
const props = withDefaults(defineProps<{ prefix: string; compact?: boolean; context?: string }>(), { compact: false, context: undefined });
const { t } = useTrans();
const currency = usePage<SharedProps>().props.site.currency;

const key = (field: string) => `${props.prefix}.${field}`;
const id = (field: string) => `${props.prefix.replace(/\./g, '-')}-${field}`;
const discount = computed(() => {
    const regular = Number(model.value.regular_price);
    const sale = Number(model.value.sale_price);
    return regular > 0 && sale > 0 && sale < regular ? Math.round((1 - sale / regular) * 100) : 0;
});
const scheduleSale = ref(!!(model.value.sale_starts_at || model.value.sale_ends_at));
</script>

<template>
    <div>
        <FormSection :title="compact ? undefined : t('forms.product.pricing')">
            <div class="grid gap-5 sm:grid-cols-2">
                <FormField :id="id('regular_price')" :label="t('admin.regular_price')" :error-key="key('regular_price')" :context="context" required>
                    <div class="kt-input-group">
                        <input :id="id('regular_price')" v-model="model.regular_price" type="number" step="0.01" min="0" inputmode="decimal" class="kt-input" dir="ltr" />
                        <span class="kt-input-addon font-mono text-xs">{{ currency }}</span>
                    </div>
                </FormField>
                <FormField :id="id('sale_price')" :label="t('admin.sale_price')" :error-key="key('sale_price')" :context="context" optional>
                    <template #default>
                        <div class="kt-input-group">
                            <input :id="id('sale_price')" v-model="model.sale_price" type="number" step="0.01" min="0" inputmode="decimal" class="kt-input" dir="ltr" />
                            <span class="kt-input-addon font-mono text-xs">{{ currency }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span v-if="discount" class="kt-badge kt-badge-sm kt-badge-success kt-badge-outline">{{ t('forms.product.discount', { percent: discount }) }}</span>
                            <button v-if="!scheduleSale" type="button" class="ms-auto text-xs font-medium text-primary hover:underline" @click="scheduleSale = true">
                                {{ t('forms.product.sale_schedule') }}
                            </button>
                        </div>
                    </template>
                </FormField>
                <template v-if="scheduleSale">
                    <FormField :id="id('sale_starts_at')" :label="t('forms.product.sale_starts_at')" :error-key="key('sale_starts_at')" :context="context">
                        <input :id="id('sale_starts_at')" v-model="model.sale_starts_at" type="datetime-local" class="kt-input" />
                    </FormField>
                    <FormField :id="id('sale_ends_at')" :label="t('forms.product.sale_ends_at')" :error-key="key('sale_ends_at')" :context="context">
                        <input :id="id('sale_ends_at')" v-model="model.sale_ends_at" type="datetime-local" class="kt-input" />
                    </FormField>
                </template>
            </div>
        </FormSection>

        <FormSection :title="t('forms.product.inventory')">
            <div class="grid gap-5 sm:grid-cols-2">
                <FormField :id="id('sku')" :label="t('admin.sku')" :error-key="key('sku')" :context="context" optional>
                    <input :id="id('sku')" v-model="model.sku" class="kt-input font-mono" maxlength="64" dir="ltr" />
                </FormField>
                <FormField v-if="model.manage_stock" :id="id('stock_quantity')" :label="t('admin.stock_quantity')" :error-key="key('stock_quantity')" :context="context">
                    <input :id="id('stock_quantity')" v-model.number="model.stock_quantity" type="number" step="1" inputmode="numeric" class="kt-input" dir="ltr" />
                </FormField>
                <FormField v-else :id="id('stock_status')" :label="t('admin.stock_status')" :error-key="key('stock_status')" :context="context">
                    <select :id="id('stock_status')" v-model="model.stock_status" class="kt-select">
                        <option v-for="s in ['in_stock', 'out_of_stock', 'on_backorder']" :key="s" :value="s">{{ t(`admin.stock_${s}`) }}</option>
                    </select>
                </FormField>
                <SwitchRow :id="id('manage_stock')" v-model="model.manage_stock" :label="t('admin.manage_stock')" :description="t('forms.product.manage_stock_help')" class="sm:col-span-2" />
            </div>
        </FormSection>

        <FormSection v-if="!compact" :title="t('forms.product.shipping')" :description="t('forms.product.shipping_help')">
            <div class="grid grid-cols-2 gap-5 md:grid-cols-4">
                <FormField
                    v-for="[field, unit, label] in [
                        ['weight_grams', 'g', 'weight'],
                        ['length_mm', 'mm', 'length'],
                        ['width_mm', 'mm', 'width'],
                        ['height_mm', 'mm', 'height'],
                    ] as const"
                    :id="id(field)"
                    :key="field"
                    :label="t(`forms.product.${label}`)"
                    :error-key="key(field)"
                    :context="context"
                >
                    <div class="kt-input-group">
                        <input :id="id(field)" v-model.number="model[field]" type="number" min="0" inputmode="numeric" class="kt-input" dir="ltr" />
                        <span class="kt-input-addon text-xs">{{ t(`forms.product.units_${unit}`) }}</span>
                    </div>
                </FormField>
            </div>
        </FormSection>

        <TierPriceFields v-model="model" :prefix="prefix" />
    </div>
</template>
