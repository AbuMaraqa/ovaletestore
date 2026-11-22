<script setup lang="ts">
import FormField from '@/Components/Forms/FormField.vue';
import { useTrans } from '@/Composables/useTrans';
import type { VariantForm } from '@/types/admin';

/** Price / SKU / stock / shipping fields for one purchasable variant (also used for simple products). */
const model = defineModel<VariantForm>({ required: true });
const props = withDefaults(defineProps<{ errors: Record<string, string>; prefix: string; compact?: boolean }>(), { compact: false });
const { t } = useTrans();
const e = (field: string) => props.errors[`${props.prefix}.${field}`];
const id = (field: string) => `${props.prefix.replace('.', '-')}-${field}`;
</script>

<template>
    <div class="grid gap-5 md:grid-cols-3">
        <FormField :id="id('regular_price')" :label="t('admin.regular_price')" :error="e('regular_price')" required>
            <input :id="id('regular_price')" v-model="model.regular_price" type="number" step="0.01" min="0" class="kt-input" dir="ltr" />
        </FormField>
        <FormField :id="id('sale_price')" :label="t('admin.sale_price')" :error="e('sale_price')">
            <input :id="id('sale_price')" v-model="model.sale_price" type="number" step="0.01" min="0" class="kt-input" dir="ltr" />
        </FormField>
        <FormField :id="id('sku')" :label="t('admin.sku')" :error="e('sku')">
            <input :id="id('sku')" v-model="model.sku" class="kt-input font-mono" maxlength="64" dir="ltr" />
        </FormField>

        <FormField :id="id('stock_status')" :label="t('admin.stock_status')" :error="e('stock_status')">
            <select :id="id('stock_status')" v-model="model.stock_status" class="kt-select">
                <option v-for="s in ['in_stock', 'out_of_stock', 'on_backorder']" :key="s" :value="s">{{ t(`admin.stock_${s}`) }}</option>
            </select>
        </FormField>
        <div class="flex items-end pb-2.5">
            <label class="flex items-center gap-3 text-sm">
                <input v-model="model.manage_stock" type="checkbox" class="kt-switch" />
                {{ t('admin.manage_stock') }}
            </label>
        </div>
        <FormField v-if="model.manage_stock" :id="id('stock_quantity')" :label="t('admin.stock_quantity')" :error="e('stock_quantity')">
            <input :id="id('stock_quantity')" v-model.number="model.stock_quantity" type="number" step="1" class="kt-input" dir="ltr" />
        </FormField>

        <template v-if="!compact">
            <FormField :id="id('weight')" :label="t('admin.weight')" :error="e('weight_grams')">
                <input :id="id('weight')" v-model.number="model.weight_grams" type="number" min="0" class="kt-input" dir="ltr" />
            </FormField>
            <div class="grid grid-cols-3 gap-2 md:col-span-2">
                <FormField :id="id('length')" :label="t('admin.length')"><input :id="id('length')" v-model.number="model.length_mm" type="number" min="0" class="kt-input" dir="ltr" /></FormField>
                <FormField :id="id('width')" :label="t('admin.width')"><input :id="id('width')" v-model.number="model.width_mm" type="number" min="0" class="kt-input" dir="ltr" /></FormField>
                <FormField :id="id('height')" :label="t('admin.height')"><input :id="id('height')" v-model.number="model.height_mm" type="number" min="0" class="kt-input" dir="ltr" /></FormField>
            </div>
        </template>
    </div>
</template>
