<script setup lang="ts">
import { computed, ref } from 'vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import { useTrans } from '@/Composables/useTrans';

const categoryIds = defineModel<number[]>('categoryIds', { required: true });
const primaryCategoryId = defineModel<number | null>('primaryCategoryId', { required: true });
const brandId = defineModel<number | null>('brandId', { required: true });
const props = defineProps<{ categories: { id: number; name: string; parent_id: number | null }[]; brands: { id: number; name: string }[]; errors: Record<string, string> }>();
const { t } = useTrans();

const filter = ref('');
const visible = computed(() => props.categories.filter((c) => c.name.toLowerCase().includes(filter.value.toLowerCase())));
const selected = computed(() => props.categories.filter((c) => categoryIds.value.includes(c.id)));

function toggle(id: number): void {
    categoryIds.value = categoryIds.value.includes(id) ? categoryIds.value.filter((c) => c !== id) : [...categoryIds.value, id];
    if (!categoryIds.value.includes(primaryCategoryId.value ?? -1)) primaryCategoryId.value = categoryIds.value[0] ?? null;
}
</script>

<template>
    <KtCard :title="t('admin.categories')">
        <div class="space-y-4">
            <input v-model="filter" type="search" class="kt-input" :placeholder="t('common.search')" :aria-label="t('common.search')" />
            <div class="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-border p-2">
                <label v-for="c in visible" :key="c.id" :class="['flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-accent', c.parent_id ? 'ps-6' : '']">
                    <input type="checkbox" class="kt-checkbox kt-checkbox-sm" :checked="categoryIds.includes(c.id)" @change="toggle(c.id)" />
                    {{ c.name }}
                </label>
            </div>
            <p v-if="errors.category_ids" class="text-sm text-destructive">{{ errors.category_ids }}</p>

            <FormField v-if="selected.length > 1" id="primary_category" :label="t('admin.primary_category')" :error="errors.primary_category_id">
                <select id="primary_category" v-model="primaryCategoryId" class="kt-select">
                    <option v-for="c in selected" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </FormField>

            <FormField id="brand" :label="t('admin.brand')" :error="errors.brand_id">
                <select id="brand" v-model="brandId" class="kt-select">
                    <option :value="null">—</option>
                    <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </FormField>
        </div>
    </KtCard>
</template>
