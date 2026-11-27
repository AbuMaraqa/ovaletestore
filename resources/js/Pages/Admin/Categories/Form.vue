<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import SeoPanel, { type SeoOverrides } from '@/Components/Admin/Seo/SeoPanel.vue';
import ErrorSummary from '@/Components/Forms/ErrorSummary.vue';
import FormCard from '@/Components/Forms/FormCard.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import FormToolbar from '@/Components/Forms/FormToolbar.vue';
import ImageInput from '@/Components/Forms/ImageInput.vue';
import LocaleTabs from '@/Components/Forms/LocaleTabs.vue';
import RichTextEditor from '@/Components/Forms/RichTextEditor.vue';
import SwitchRow from '@/Components/Forms/SwitchRow.vue';
import { useFormKit } from '@/Composables/useFormKit';
import { useTrans } from '@/Composables/useTrans';

type Fields = { name: string; slug: string; description: string; meta_title: string; meta_description: string };
const props = defineProps<{
    category: { id: number; parent_id: number | null; sort_order: number; is_active: boolean; image: string | null; translations: Record<string, Fields> } | null;
    parents: { id: number; name: string }[];
    seo_overrides?: SeoOverrides;
}>();

const { t } = useTrans();
const blank = (): Fields => ({ name: '', slug: '', description: '', meta_title: '', meta_description: '' });
const names: Record<string, string> = { ar: 'العربية', en: 'English', he: 'עברית' };
const form = useForm({
    parent_id: props.category?.parent_id ?? null,
    sort_order: props.category?.sort_order ?? 0,
    is_active: props.category?.is_active ?? true,
    image: null as File | null,
    translations: props.category?.translations ?? { ar: blank(), en: blank(), he: blank() },
    _method: props.category ? 'put' : 'post',
});
const { focusFirstError } = useFormKit(form, { submit: () => submit(), guard: true });

// Live Google preview in the search-engine card: the same fallbacks as the server (meta field → content).
const seoTexts = computed(() =>
    Object.fromEntries(
        Object.entries(form.translations).map(([l, f]) => [l, { title: f.meta_title || f.name, description: f.meta_description || f.description.replace(/<[^>]*>/g, ' ') }]),
    ),
);

function submit(): void {
    // POST + _method so file uploads work for updates too.
    const url = props.category ? route('admin.categories.update', props.category.id) : route('admin.categories.store');
    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onError: () => void focusFirstError(),
        onSuccess: () => {
            form.image = null;
            form.defaults();
        },
    });
}
</script>

<template>
    <form id="category-form" novalidate @submit.prevent="submit">
        <FormToolbar
            :title="category ? form.translations.ar.name || t('admin.edit_category') : t('admin.new_category')"
            :back-href="route('admin.categories.index')"
            form-id="category-form"
            :dirty="form.isDirty"
            :processing="form.processing"
            @discard="form.reset()"
        />
        <ErrorSummary class="mb-5" />

        <div class="grid items-start gap-5 lg:gap-7.5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <FormCard :title="t('admin.content')" :description="t('admin.translations_help')" icon="document" :errors="['translations']">
                <LocaleTabs>
                    <template #default="{ locale }">
                        <FormSection>
                            <div class="grid gap-5">
                                <FormField :id="`n-${locale}`" :label="t('admin.name')" :error-key="`translations.${locale}.name`" :context="names[locale]" :required="locale === 'ar'">
                                    <input :id="`n-${locale}`" v-model="form.translations[locale].name" class="kt-input kt-input-lg text-base" maxlength="255" />
                                </FormField>
                                <FormField :id="`d-${locale}`" :label="t('admin.description')" :error-key="`translations.${locale}.description`" :context="names[locale]" optional>
                                    <RichTextEditor :id="`d-${locale}`" v-model="form.translations[locale].description" />
                                </FormField>
                            </div>
                        </FormSection>
                        <FormSection :title="t('forms.product.seo')" :description="t('forms.product.seo_help')">
                            <div class="grid gap-5">
                                <FormField :id="`s-${locale}`" :label="t('forms.product.url')" :help="t('forms.product.url_help')" :error-key="`translations.${locale}.slug`" :context="names[locale]">
                                    <div class="kt-input-group" dir="ltr">
                                        <span class="kt-input-addon text-xs">/{{ locale }}/product-category/</span>
                                        <input :id="`s-${locale}`" v-model="form.translations[locale].slug" class="kt-input font-mono text-xs" maxlength="160" />
                                    </div>
                                </FormField>
                                <div class="grid gap-5 md:grid-cols-2">
                                    <FormField
                                        :id="`mt-${locale}`"
                                        :label="t('admin.meta_title')"
                                        :error-key="`translations.${locale}.meta_title`"
                                        :context="names[locale]"
                                        :counter="{ value: form.translations[locale].meta_title.length, max: 60 }"
                                        optional
                                    >
                                        <input :id="`mt-${locale}`" v-model="form.translations[locale].meta_title" class="kt-input" maxlength="70" />
                                    </FormField>
                                    <FormField
                                        :id="`md-${locale}`"
                                        :label="t('admin.meta_description')"
                                        :error-key="`translations.${locale}.meta_description`"
                                        :context="names[locale]"
                                        :counter="{ value: form.translations[locale].meta_description.length, max: 160 }"
                                        optional
                                    >
                                        <textarea :id="`md-${locale}`" v-model="form.translations[locale].meta_description" class="kt-textarea" rows="2" maxlength="170" />
                                    </FormField>
                                </div>
                            </div>
                        </FormSection>
                    </template>
                </LocaleTabs>
            </FormCard>

            <FormCard :title="t('admin.general')" icon="setting-2" :errors="['parent_id', 'sort_order', 'image', 'is_active']">
                <div class="grid gap-5">
                    <FormField id="image" :label="t('admin.images')" error-key="image" optional>
                        <ImageInput id="image" v-model="form.image" :current="category?.image" />
                    </FormField>
                    <FormField id="parent" :label="t('admin.parent_category')" error-key="parent_id">
                        <select id="parent" v-model="form.parent_id" class="kt-select">
                            <option :value="null">{{ t('admin.no_parent') }}</option>
                            <option v-for="p in parents" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </FormField>
                    <SwitchRow id="is_active" v-model="form.is_active" :label="t('admin.active')" />
                    <FormField id="sort" :label="t('admin.sort_order')" error-key="sort_order">
                        <input id="sort" v-model.number="form.sort_order" type="number" min="0" class="kt-input" dir="ltr" />
                    </FormField>
                </div>
            </FormCard>
        </div>
    </form>
    <SeoPanel v-if="seo_overrides" class="mt-5 lg:mt-7.5" :data="seo_overrides" :texts="seoTexts" />
</template>
