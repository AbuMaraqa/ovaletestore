<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, provide } from 'vue';
import ProductMedia from '@/Components/Admin/Products/ProductMedia.vue';
import ProductOrganization from '@/Components/Admin/Products/ProductOrganization.vue';
import ProductTranslations from '@/Components/Admin/Products/ProductTranslations.vue';
import VariantFields from '@/Components/Admin/Products/VariantFields.vue';
import VariationsEditor from '@/Components/Admin/Products/VariationsEditor.vue';
import SeoPanel, { type SeoOverrides } from '@/Components/Admin/Seo/SeoPanel.vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';
import { emptyVariant, type AttributeOption, type PriceTierOption, type ProductForm } from '@/types/admin';

const props = defineProps<{
    product: ProductForm | null;
    categories: { id: number; name: string; parent_id: number | null }[];
    brands: { id: number; name: string }[];
    attributes: AttributeOption[];
    locales: string[];
    tiers: PriceTierOption[];
    seo_overrides?: SeoOverrides;
}>();

// Trader price fields inside every variant read the tiers from here.
provide('priceTiers', computed(() => props.tiers));

const { t } = useTrans();
const blankTranslation = () => ({ name: '', slug: '', short_description: '', description: '', meta_title: '', meta_description: '' });

const form = useForm<ProductForm>(
    props.product
        ? // JSON round-trip, not structuredClone: page props are reactive proxies, which structuredClone rejects.
          (JSON.parse(JSON.stringify({ ...props.product, media: undefined, view_url: undefined })) as ProductForm)
        : {
              type: 'simple',
              status: 'draft',
              brand_id: null,
              is_featured: false,
              published_at: null,
              translations: { ar: blankTranslation(), en: blankTranslation(), he: blankTranslation() },
              category_ids: [],
              primary_category_id: null,
              attributes: [],
              variants: [{ ...emptyVariant(), is_default: true }],
          },
);

const isEdit = computed(() => !!props.product?.id);
const errors = computed(() => form.errors as Record<string, string>);

// Live Google preview in the search-engine card: the same fallbacks as the server (meta field → content).
const seoTexts = computed(() =>
    Object.fromEntries(
        Object.entries(form.translations).map(([l, f]) => [l, { title: f.meta_title || f.name, description: f.meta_description || f.short_description || f.description }]),
    ),
);

function submit(): void {
    const options = { preserveScroll: true };
    isEdit.value ? form.put(route('admin.products.update', props.product!.id!), options) : form.post(route('admin.products.store'), options);
}

function onTypeChange(): void {
    if (form.type === 'simple') {
        form.attributes = form.attributes.map((a) => ({ ...a, used_for_variations: false }));
        form.variants = [{ ...(form.variants.find((v) => v.is_default) ?? form.variants[0] ?? emptyVariant()), term_ids: [], is_default: true }];
    }
}
</script>

<template>
    <PageHeader :title="isEdit ? t('admin.edit_product') : t('admin.new_product')">
        <template #actions>
            <a v-if="product?.view_url" :href="product.view_url" target="_blank" rel="noopener" class="kt-btn kt-btn-outline">{{ t('admin.view_store') }}</a>
            <KtButton :href="route('admin.products.index')" variant="ghost">{{ t('common.back') }}</KtButton>
            <KtButton type="submit" form="product-form" :loading="form.processing" icon="check">{{ t('common.save') }}</KtButton>
        </template>
    </PageHeader>

    <form id="product-form" class="grid gap-6 xl:grid-cols-12" novalidate @submit.prevent="submit">
        <div class="space-y-6 xl:col-span-8">
            <ProductTranslations v-model="form.translations" :errors="errors" :locales="locales" />

            <KtCard v-if="form.type === 'simple'" :title="t('admin.pricing')">
                <VariantFields v-model="form.variants[0]" :errors="errors" prefix="variants.0" />
            </KtCard>

            <VariationsEditor v-else v-model:attributes="form.attributes" v-model:variants="form.variants" :options="attributes" :errors="errors" />

            <ProductMedia v-if="isEdit" :product-id="product!.id!" :media="product!.media ?? []" />
            <KtCard v-else :title="t('admin.product_photos')">
                <p class="flex items-center gap-3 rounded-lg border border-dashed border-border bg-muted/40 p-5 text-sm text-secondary-foreground">
                    <KtIcon name="picture" class="text-2xl text-muted-foreground" />
                    {{ t('admin.save_first_for_photos') }}
                </p>
            </KtCard>
        </div>

        <aside class="space-y-6 xl:col-span-4">
            <KtCard :title="t('common.status')">
                <div class="space-y-4">
                    <FormField id="status" :label="t('common.status')" :error="errors.status">
                        <select id="status" v-model="form.status" class="kt-select">
                            <option v-for="s in ['draft', 'published', 'archived']" :key="s" :value="s">{{ t(`admin.status_${s}`) }}</option>
                        </select>
                    </FormField>
                    <FormField id="type" :label="t('admin.type')" :error="errors.type">
                        <select id="type" v-model="form.type" class="kt-select" @change="onTypeChange">
                            <option value="simple">{{ t('admin.type_simple') }}</option>
                            <option value="variable">{{ t('admin.type_variable') }}</option>
                        </select>
                    </FormField>
                    <label class="flex items-center justify-between gap-3 text-sm">
                        {{ t('admin.featured') }}
                        <input v-model="form.is_featured" type="checkbox" class="kt-switch" />
                    </label>
                    <FormField id="published_at" :label="t('admin.date')" :error="errors.published_at">
                        <input id="published_at" v-model="form.published_at" type="datetime-local" class="kt-input" />
                    </FormField>
                </div>
            </KtCard>

            <ProductOrganization
                v-model:category-ids="form.category_ids"
                v-model:primary-category-id="form.primary_category_id"
                v-model:brand-id="form.brand_id"
                :categories="categories"
                :brands="brands"
                :errors="errors"
            />
        </aside>
    </form>
    <SeoPanel v-if="seo_overrides" class="mt-6" :data="seo_overrides" :texts="seoTexts" />
</template>
