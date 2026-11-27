<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import VariantFields from '@/Components/Admin/Products/VariantFields.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCollapse from '@/Components/UI/KtCollapse.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useFormKitContext } from '@/Composables/useFormKit';
import { useTrans } from '@/Composables/useTrans';
import { emptyVariant, type AttributeOption, type ProductAttributeForm, type VariantForm } from '@/types/admin';

/**
 * Variable product: 1) pick attributes + the terms this product offers, 2) generate the combinations,
 * 3) price each one. Existing variants are kept when their combination still exists (sorted term ids);
 * the server re-validates every combination (SyncProductVariants). A row with errors opens itself.
 */
const attributes = defineModel<ProductAttributeForm[]>('attributes', { required: true });
const variants = defineModel<VariantForm[]>('variants', { required: true });
const props = defineProps<{ options: AttributeOption[] }>();
const { t } = useTrans();
const { confirm } = useConfirm();
const kit = useFormKitContext();

const toAdd = ref<number | ''>('');
const expanded = ref<number | null>(null);
const available = computed(() => props.options.filter((o) => !attributes.value.some((a) => a.attribute_id === o.id)));
const option = (id: number) => props.options.find((o) => o.id === id);
const termName = (termId: number) => props.options.flatMap((o) => o.terms).find((term) => term.id === termId)?.name ?? `#${termId}`;
const signature = (ids: number[]) => [...ids].sort((a, b) => a - b).join('-');
const label = (v: VariantForm) => v.term_ids.map(termName).join(' / ');
const rowErrors = (i: number) => kit?.count([`variants.${i}`]) ?? 0;
const error = (key: string) => kit?.errors.value[key];

let off: (() => void) | undefined;
onMounted(() => {
    off = kit?.addRevealer((key) => {
        const match = key.match(/^variants\.(\d+)\./);
        if (match) expanded.value = Number(match[1]);
        return !!match;
    });
});
onBeforeUnmount(() => off?.());

function addAttribute(): void {
    if (toAdd.value === '') return;
    attributes.value = [...attributes.value, { attribute_id: toAdd.value, term_ids: [], used_for_variations: true, is_visible: true }];
    toAdd.value = '';
}
async function removeAttribute(i: number): Promise<void> {
    if (await confirm({ title: t('forms.product.remove_attribute'), message: option(attributes.value[i].attribute_id)?.name, confirmLabel: t('common.delete') })) attributes.value.splice(i, 1);
}
async function removeVariant(i: number): Promise<void> {
    if (await confirm({ title: t('forms.product.remove_variant'), message: label(variants.value[i]), confirmLabel: t('common.delete') })) variants.value.splice(i, 1);
}
function toggleTerm(row: ProductAttributeForm, termId: number): void {
    row.term_ids = row.term_ids.includes(termId) ? row.term_ids.filter((id) => id !== termId) : [...row.term_ids, termId];
}
function generate(): void {
    const sets = attributes.value.filter((a) => a.used_for_variations && a.term_ids.length).map((a) => a.term_ids);
    if (!sets.length) return;
    const combos = sets.reduce<number[][]>((acc, ids) => acc.flatMap((combo) => ids.map((id) => [...combo, id])), [[]]);
    const existing = new Map(variants.value.map((v) => [signature(v.term_ids), v]));
    const template = variants.value[0];
    variants.value = combos.map((combo, i) => {
        const found = existing.get(signature(combo));
        if (found) return { ...found, term_ids: combo };
        return { ...emptyVariant(combo), regular_price: template?.regular_price ?? null, is_default: i === 0 && !existing.size };
    });
}
const pending = computed(() => attributes.value.filter((a) => a.used_for_variations && a.term_ids.length).reduce((n, a) => n * a.term_ids.length, 1));
</script>

<template>
    <FormSection :title="t('admin.variation_attributes')" :description="t('admin.variation_attributes_help')">
        <div class="space-y-3">
            <div v-for="(row, i) in attributes" :key="row.attribute_id" class="rounded-lg border border-border p-4" :data-field-key="`attributes.${i}.term_ids`">
                <div class="flex items-center justify-between gap-3">
                    <p class="font-medium text-mono">{{ option(row.attribute_id)?.name }}</p>
                    <div class="flex items-center gap-3 text-sm">
                        <label class="flex items-center gap-2"><input v-model="row.used_for_variations" type="checkbox" class="kt-checkbox kt-checkbox-sm" /> {{ t('admin.variations') }}</label>
                        <button type="button" class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost text-destructive" :aria-label="t('common.delete')" @click="removeAttribute(i)"><KtIcon name="trash" /></button>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2" role="group" :aria-label="option(row.attribute_id)?.name">
                    <button
                        v-for="term in option(row.attribute_id)?.terms ?? []"
                        :key="term.id"
                        type="button"
                        :aria-pressed="row.term_ids.includes(term.id)"
                        :class="['kt-btn kt-btn-sm', row.term_ids.includes(term.id) ? '' : 'kt-btn-outline']"
                        @click="toggleTerm(row, term.id)"
                    >
                        {{ term.name }}
                    </button>
                </div>
                <p v-if="error(`attributes.${i}.term_ids`)" class="form-field-error mt-2">{{ error(`attributes.${i}.term_ids`) }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select v-model="toAdd" class="kt-select w-56" :aria-label="t('admin.select_attribute')">
                    <option value="">{{ t('admin.select_attribute') }}</option>
                    <option v-for="o in available" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
                <KtButton variant="outline" icon="plus" :disabled="toAdd === ''" @click="addAttribute">{{ t('forms.product.add_attribute') }}</KtButton>
                <KtButton variant="secondary" icon="abstract-26" class="ms-auto" :disabled="!attributes.length" @click="generate">
                    {{ t('admin.generate_variations') }} <span v-if="attributes.length" class="tabular-nums">({{ pending }})</span>
                </KtButton>
            </div>
        </div>
    </FormSection>

    <FormSection :title="`${t('admin.variations')} (${variants.length})`">
        <p v-if="error('variants')" class="form-field-error mb-3">{{ error('variants') }}</p>
        <p v-if="!variants.length || !variants[0].term_ids.length" class="rounded-lg border border-dashed border-border p-5 text-center text-sm text-muted-foreground">
            {{ t('admin.no_variations') }}
        </p>
        <div v-else class="divide-y divide-border rounded-lg border border-border">
            <div v-for="(variant, i) in variants" :key="signature(variant.term_ids)" :class="['p-3.5', !variant.is_active && 'bg-muted/30']">
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="flex min-w-0 flex-1 items-center gap-2.5 text-start" :aria-expanded="expanded === i" @click="expanded = expanded === i ? null : i">
                        <KtIcon name="down" :class="['text-muted-foreground transition-transform', expanded === i && 'rotate-180']" />
                        <span class="truncate font-medium text-mono">{{ label(variant) }}</span>
                        <span class="text-sm tabular-nums text-muted-foreground" dir="ltr">{{ variant.regular_price || '—' }}</span>
                        <span v-if="rowErrors(i)" class="kt-badge kt-badge-xs kt-badge-destructive">{{ rowErrors(i) }}</span>
                    </button>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" name="default-variant" :checked="variant.is_default" class="kt-radio kt-radio-sm" @change="variants.forEach((v, j) => (v.is_default = j === i))" /> {{ t('admin.default') }}</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="variant.is_active" type="checkbox" class="kt-switch kt-switch-sm" /> {{ t('admin.active') }}</label>
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost text-destructive" :aria-label="t('common.delete')" @click="removeVariant(i)"><KtIcon name="trash" /></button>
                </div>
                <p v-if="error(`variants.${i}.term_ids`)" class="form-field-error mt-2">{{ error(`variants.${i}.term_ids`) }}</p>
                <KtCollapse :open="expanded === i">
                    <div class="pt-4"><VariantFields v-model="variants[i]" :prefix="`variants.${i}`" :context="label(variant)" compact /></div>
                </KtCollapse>
            </div>
        </div>
    </FormSection>
</template>
