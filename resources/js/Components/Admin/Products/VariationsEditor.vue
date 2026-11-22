<script setup lang="ts">
import { computed, ref } from 'vue';
import VariantFields from '@/Components/Admin/Products/VariantFields.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import { useTrans } from '@/Composables/useTrans';
import { emptyVariant, type AttributeOption, type ProductAttributeForm, type VariantForm } from '@/types/admin';

/**
 * 1) choose global attributes + the terms this product offers, 2) generate combinations.
 * Existing variants are kept when their term combination still exists (matched by sorted term ids);
 * the server re-validates every combination (SyncProductVariants).
 */
const attributes = defineModel<ProductAttributeForm[]>('attributes', { required: true });
const variants = defineModel<VariantForm[]>('variants', { required: true });
const props = defineProps<{ options: AttributeOption[]; errors: Record<string, string> }>();
const { t } = useTrans();

const toAdd = ref<number | ''>('');
const expanded = ref<number | null>(null);
const available = computed(() => props.options.filter((o) => !attributes.value.some((a) => a.attribute_id === o.id)));
const option = (id: number) => props.options.find((o) => o.id === id);
const termName = (termId: number) => props.options.flatMap((o) => o.terms).find((term) => term.id === termId)?.name ?? `#${termId}`;
const signature = (ids: number[]) => [...ids].sort((a, b) => a - b).join('-');

function addAttribute(): void {
    if (toAdd.value === '') return;
    attributes.value = [...attributes.value, { attribute_id: toAdd.value, term_ids: [], used_for_variations: true, is_visible: true }];
    toAdd.value = '';
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

function setDefault(index: number): void {
    variants.value.forEach((v, i) => (v.is_default = i === index));
}
</script>

<template>
    <KtCard :title="t('admin.variation_attributes')" :description="t('admin.variation_attributes_help')">
        <div class="space-y-4">
            <div v-for="(row, i) in attributes" :key="row.attribute_id" class="rounded-lg border border-border p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="font-medium text-mono">{{ option(row.attribute_id)?.name }}</p>
                    <div class="flex items-center gap-4 text-sm">
                        <label class="flex items-center gap-2"><input v-model="row.used_for_variations" type="checkbox" class="kt-checkbox" /> {{ t('admin.variations') }}</label>
                        <button type="button" class="kt-btn kt-btn-sm kt-btn-ghost text-destructive" @click="attributes.splice(i, 1)">{{ t('common.delete') }}</button>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
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
                <p v-if="errors[`attributes.${i}.term_ids`]" class="mt-2 text-sm text-destructive">{{ errors[`attributes.${i}.term_ids`] }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <select v-model="toAdd" class="kt-select w-60" :aria-label="t('admin.select_attribute')">
                    <option value="">{{ t('admin.select_attribute') }}</option>
                    <option v-for="o in available" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
                <KtButton variant="outline" icon="plus" :disabled="toAdd === ''" @click="addAttribute">{{ t('common.create') }}</KtButton>
                <KtButton variant="secondary" icon="abstract-26" class="ms-auto" @click="generate">{{ t('admin.generate_variations') }}</KtButton>
            </div>
        </div>
    </KtCard>

    <KtCard :title="`${t('admin.variations')} (${variants.length})`">
        <p v-if="errors.variants" class="mb-3 text-sm text-destructive">{{ errors.variants }}</p>
        <p v-if="!variants.length || !variants[0].term_ids.length" class="text-sm text-muted-foreground">{{ t('admin.no_variations') }}</p>
        <div v-else class="divide-y divide-border rounded-lg border border-border">
            <div v-for="(variant, i) in variants" :key="signature(variant.term_ids)" class="p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="flex flex-1 items-center gap-2 text-start" :aria-expanded="expanded === i" @click="expanded = expanded === i ? null : i">
                        <span class="font-medium text-mono">{{ variant.term_ids.map(termName).join(' / ') }}</span>
                        <span class="text-sm text-muted-foreground" dir="ltr">{{ variant.regular_price ?? '—' }}</span>
                        <span v-if="Object.keys(errors).some((k) => k.startsWith(`variants.${i}.`))" class="size-2 rounded-full bg-destructive" />
                    </button>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" name="default-variant" :checked="variant.is_default" class="kt-radio" @change="setDefault(i)" /> {{ t('admin.default') }}</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="variant.is_active" type="checkbox" class="kt-switch kt-switch-sm" /> {{ t('admin.active') }}</label>
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-ghost text-destructive" @click="variants.splice(i, 1)">{{ t('common.delete') }}</button>
                </div>
                <p v-if="errors[`variants.${i}.term_ids`]" class="mt-2 text-sm text-destructive">{{ errors[`variants.${i}.term_ids`] }}</p>
                <div v-show="expanded === i" class="mt-4">
                    <VariantFields v-model="variants[i]" :errors="errors" :prefix="`variants.${i}`" compact />
                </div>
            </div>
        </div>
    </KtCard>
</template>
