<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, provide, ref } from 'vue';
import ProductContent from '@/Components/Admin/Products/ProductContent.vue';
import ProductOrganization from '@/Components/Admin/Products/ProductOrganization.vue';
import ProductPublishing from '@/Components/Admin/Products/ProductPublishing.vue';
import ProductSeoCard from '@/Components/Admin/Products/ProductSeoCard.vue';
import VariantFields from '@/Components/Admin/Products/VariantFields.vue';
import VariationsEditor from '@/Components/Admin/Products/VariationsEditor.vue';
import type { SeoOverrides } from '@/Components/Admin/Seo/SeoPanel.vue';
import ErrorSummary from '@/Components/Forms/ErrorSummary.vue';
import FormCard from '@/Components/Forms/FormCard.vue';
import FormToolbar from '@/Components/Forms/FormToolbar.vue';
import MediaGallery from '@/Components/Forms/MediaGallery.vue';
import SegmentedControl from '@/Components/Forms/SegmentedControl.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useFormKit } from '@/Composables/useFormKit';
import { useTrans } from '@/Composables/useTrans';
import { emptyVariant, type AttributeOption, type PriceTierOption, type ProductForm } from '@/types/admin';

/**
 * Add / edit a product on one screen: content → images → price & inventory → search engines, with publishing and
 * categories on the side. One Save (sticky bar, Ctrl+S) stores everything, images included (staged while picked).
 * Validation: errors next to each field + a summary that jumps to them (Components/Forms, useFormKit).
 */
const props = defineProps<{
    product: ProductForm | null;
    categories: { id: number; name: string; parent_id: number | null }[];
    brands: { id: number; name: string }[];
    attributes: AttributeOption[];
    locales: string[];
    tiers: PriceTierOption[];
    seo_overrides?: SeoOverrides;
}>();

provide('priceTiers', computed(() => props.tiers)); // trader price fields inside every variant
const { t } = useTrans();
const { confirm } = useConfirm();
const blankTranslation = () => ({ name: '', slug: '', short_description: '', description: '', meta_title: '', meta_description: '' });

const form = useForm<ProductForm>({
    ...(props.product
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
          }),
    uploads: [],
    seo: { robots: props.seo_overrides?.robots ?? null, canonical_url: props.seo_overrides?.canonical_url ?? '', image_upload: null, remove_image: false },
    add_another: false,
});

const isEdit = computed(() => !!props.product?.id);
const locale = ref(props.locales[0] ?? 'ar');
const gallery = ref<InstanceType<typeof MediaGallery> | null>(null);
const uploading = computed(() => !!gallery.value?.busy);

const { focusFirstError } = useFormKit(form, { submit: () => submit(), guard: true });

function submit(addAnother = false): void {
    if (uploading.value) return;
    form.add_another = addAnother;
    const options = {
        preserveScroll: true,
        onError: () => void focusFirstError(),
        onSuccess: () => {
            gallery.value?.reset();
            form.uploads = [];
            form.seo = { robots: form.seo?.robots ?? null, canonical_url: form.seo?.canonical_url ?? '', image_upload: null, remove_image: false };
            form.defaults();
        },
    };
    if (isEdit.value) form.put(route('admin.products.update', props.product!.id!), options);
    else form.post(route('admin.products.store'), options);
}

const typeOptions = [
    { value: 'simple' as const, label: t('admin.type_simple') },
    { value: 'variable' as const, label: t('admin.type_variable') },
];
const productType = computed({
    get: () => form.type,
    set: async (type) => {
        if (type === 'simple' && form.variants.length > 1) {
            const ok = await confirm({ title: t('forms.product.type_change_title'), message: t('forms.product.type_change_message'), confirmLabel: t('forms.product.type_change_confirm') });
            if (!ok) return;
        }
        form.type = type;
        if (type === 'simple') {
            form.attributes = form.attributes.map((a) => ({ ...a, used_for_variations: false }));
            form.variants = [{ ...(form.variants.find((v) => v.is_default) ?? form.variants[0] ?? emptyVariant()), term_ids: [], is_default: true }];
        }
    },
});
</script>

<template>
    <form id="product-form" novalidate @submit.prevent="submit()">
        <FormToolbar
            :title="isEdit ? form.translations.ar.name || t('admin.edit_product') : t('admin.new_product')"
            :back-href="route('admin.products.index')"
            form-id="product-form"
            :dirty="form.isDirty"
            :processing="form.processing || uploading"
            :progress="form.progress?.percentage ?? null"
            @discard="form.reset()"
        >
            <template #status>
                <span v-if="isEdit" :class="['kt-badge kt-badge-sm kt-badge-outline shrink-0', form.status === 'published' ? 'kt-badge-success' : form.status === 'archived' ? 'kt-badge-warning' : '']">
                    {{ t(`admin.status_${form.status}`) }}
                </span>
            </template>
            <template #actions>
                <a v-if="product?.view_url" :href="product.view_url" target="_blank" rel="noopener" class="kt-btn kt-btn-outline">
                    <KtIcon name="eye" /> <span class="max-md:hidden">{{ t('admin.view_store') }}</span>
                </a>
                <button v-if="!isEdit" type="button" class="kt-btn kt-btn-outline max-md:hidden" :disabled="form.processing || uploading" @click="submit(true)">
                    {{ t('forms.save_add_another') }}
                </button>
            </template>
        </FormToolbar>

        <ErrorSummary class="mb-5" />

        <div class="grid items-start gap-5 lg:gap-7.5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="grid min-w-0 gap-5 lg:gap-7.5">
                <ProductContent v-model="form.translations" v-model:locale="locale" :locales="locales" />

                <FormCard :title="t('forms.product.media')" :description="t('forms.product.media_help')" icon="picture" :errors="['uploads']">
                    <MediaGallery ref="gallery" v-model:uploads="form.uploads" :existing="product?.media ?? []" :product-id="product?.id ?? null" :error="form.errors.uploads" />
                </FormCard>

                <FormCard :title="t('forms.product.pricing_inventory')" icon="price-tag" :errors="['variants', 'attributes', 'type']">
                    <template #toolbar>
                        <SegmentedControl id="type" v-model="productType" :label="t('admin.type')" :options="typeOptions" class="w-auto" />
                    </template>
                    <VariantFields v-if="form.type === 'simple'" v-model="form.variants[0]" prefix="variants.0" />
                    <VariationsEditor v-else v-model:attributes="form.attributes" v-model:variants="form.variants" :options="attributes" />
                </FormCard>

                <ProductSeoCard
                    v-model:translations="form.translations"
                    v-model:seo="form.seo!"
                    v-model:locale="locale"
                    :locales="locales"
                    :urls="seo_overrides?.urls"
                    :image="seo_overrides?.image"
                    :robots-options="seo_overrides?.robots_options ?? ['index,follow', 'noindex,follow', 'noindex,nofollow']"
                />
            </div>

            <aside class="grid gap-5 lg:gap-7.5">
                <ProductPublishing v-model:status="form.status" v-model:published-at="form.published_at" v-model:featured="form.is_featured" />
                <ProductOrganization
                    v-model:category-ids="form.category_ids"
                    v-model:primary-category-id="form.primary_category_id"
                    v-model:brand-id="form.brand_id"
                    :categories="categories"
                    :brands="brands"
                />
            </aside>
        </div>
    </form>
</template>
