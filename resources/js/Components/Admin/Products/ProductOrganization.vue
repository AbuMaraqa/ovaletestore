<script setup lang="ts">
import { computed, ref } from 'vue';
import FormCard from '@/Components/Forms/FormCard.vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useFormKitContext } from '@/Composables/useFormKit';
import { useTrans } from '@/Composables/useTrans';

/**
 * Categories + brand. Chosen categories appear as chips on top; ★ marks the main one (breadcrumbs, URL context).
 * The list below is the real tree (indented by depth) with a search box.
 */
const categoryIds = defineModel<number[]>('categoryIds', { required: true });
const primaryCategoryId = defineModel<number | null>('primaryCategoryId', { required: true });
const brandId = defineModel<number | null>('brandId', { required: true });
const props = defineProps<{ categories: { id: number; name: string; parent_id: number | null }[]; brands: { id: number; name: string }[] }>();
const { t } = useTrans();
const kit = useFormKitContext();

const filter = ref('');
// Depth-first order so children sit under their parent, whatever order the server sent.
const tree = computed(() => {
    const byParent = new Map<number | null, typeof props.categories>();
    props.categories.forEach((c) => byParent.set(c.parent_id, [...(byParent.get(c.parent_id) ?? []), c]));
    const known = new Set(props.categories.map((c) => c.id));
    const out: { id: number; name: string; depth: number }[] = [];
    const walk = (parent: number | null, depth: number) =>
        (byParent.get(parent) ?? []).forEach((c) => {
            out.push({ id: c.id, name: c.name, depth });
            walk(c.id, depth + 1);
        });
    walk(null, 0);
    props.categories.filter((c) => c.parent_id !== null && !known.has(c.parent_id)).forEach((c) => out.push({ id: c.id, name: c.name, depth: 0 }));
    return out;
});
const visible = computed(() => {
    const q = filter.value.trim().toLowerCase();
    return q ? tree.value.filter((c) => c.name.toLowerCase().includes(q)).map((c) => ({ ...c, depth: 0 })) : tree.value;
});
const selected = computed(() => categoryIds.value.map((id) => props.categories.find((c) => c.id === id)).filter((c) => !!c));
const categoryError = computed(() => kit?.errors.value.category_ids ?? kit?.errors.value.primary_category_id);

function toggle(id: number): void {
    categoryIds.value = categoryIds.value.includes(id) ? categoryIds.value.filter((c) => c !== id) : [...categoryIds.value, id];
    if (!categoryIds.value.includes(primaryCategoryId.value ?? -1)) primaryCategoryId.value = categoryIds.value[0] ?? null;
}
</script>

<template>
    <FormCard :title="t('forms.product.organization')" icon="category" :errors="['category_ids', 'primary_category_id', 'brand_id']">
        <fieldset class="space-y-3" data-field-key="category_ids" :aria-describedby="categoryError ? 'categories-error' : undefined">
            <legend class="mb-2 text-sm font-medium text-foreground">{{ t('admin.categories') }}</legend>
            <ul v-if="selected.length" class="flex flex-wrap gap-1.5">
                <li v-for="c in selected" :key="c.id" :class="['inline-flex items-center gap-1 rounded-md border py-0.5 ps-2 pe-0.5 text-xs', c.id === primaryCategoryId ? 'border-primary/40 bg-primary/10 text-primary' : 'border-border bg-muted/40']">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1"
                        :aria-pressed="c.id === primaryCategoryId"
                        :title="t('forms.product.make_primary')"
                        @click="primaryCategoryId = c.id"
                    >
                        <KtIcon name="star" :variant="c.id === primaryCategoryId ? 'solid' : 'outline'" class="text-[13px]" />
                        {{ c.name }}
                    </button>
                    <button type="button" class="rounded p-0.5 hover:bg-accent" :aria-label="`${t('common.delete')} ${c.name}`" @click="toggle(c.id)"><KtIcon name="cross" class="text-[11px]" /></button>
                </li>
            </ul>
            <p v-else class="text-xs text-muted-foreground">{{ t('forms.product.no_categories') }}</p>

            <div class="kt-input">
                <KtIcon name="magnifier" class="text-muted-foreground" />
                <input v-model="filter" type="search" :placeholder="t('forms.product.search_categories')" :aria-label="t('forms.product.search_categories')" />
            </div>
            <div class="max-h-72 space-y-0.5 overflow-y-auto rounded-lg border border-border p-1.5">
                <label
                    v-for="c in visible"
                    :key="c.id"
                    class="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-accent"
                    :style="{ paddingInlineStart: `${0.5 + c.depth * 1.1}rem` }"
                >
                    <input type="checkbox" class="kt-checkbox kt-checkbox-sm" :checked="categoryIds.includes(c.id)" @change="toggle(c.id)" />
                    <span :class="c.depth ? 'text-secondary-foreground' : 'font-medium text-foreground'">{{ c.name }}</span>
                </label>
            </div>
            <p v-if="categoryError" id="categories-error" class="form-field-error">{{ categoryError }}</p>
        </fieldset>

        <FormField id="brand" class="mt-5" :label="t('admin.brand')" error-key="brand_id" optional>
            <select id="brand" v-model="brandId" class="kt-select">
                <option :value="null">{{ t('forms.product.no_brand') }}</option>
                <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
        </FormField>
    </FormCard>
</template>
