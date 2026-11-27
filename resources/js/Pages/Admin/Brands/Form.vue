<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import SeoPanel, { type SeoOverrides } from '@/Components/Admin/Seo/SeoPanel.vue';
import ErrorSummary from '@/Components/Forms/ErrorSummary.vue';
import FormCard from '@/Components/Forms/FormCard.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormToolbar from '@/Components/Forms/FormToolbar.vue';
import ImageInput from '@/Components/Forms/ImageInput.vue';
import LocaleTabs from '@/Components/Forms/LocaleTabs.vue';
import RichTextEditor from '@/Components/Forms/RichTextEditor.vue';
import SwitchRow from '@/Components/Forms/SwitchRow.vue';
import { useFormKit } from '@/Composables/useFormKit';
import { useTrans } from '@/Composables/useTrans';

type Fields = { name: string; slug: string; description: string };
const props = defineProps<{
    brand: { id: number; sort_order: number; is_active: boolean; is_featured: boolean; logo: string | null; translations: Record<string, Fields> } | null;
    seo_overrides?: SeoOverrides;
}>();

const { t } = useTrans();
const blank = (): Fields => ({ name: '', slug: '', description: '' });
const names: Record<string, string> = { ar: 'العربية', en: 'English', he: 'עברית' };
const form = useForm({
    sort_order: props.brand?.sort_order ?? 0,
    is_active: props.brand?.is_active ?? true,
    is_featured: props.brand?.is_featured ?? false,
    logo: null as File | null,
    translations: props.brand?.translations ?? { ar: blank(), en: blank(), he: blank() },
    _method: props.brand ? 'put' : 'post',
});
const { focusFirstError } = useFormKit(form, { submit: () => submit(), guard: true });

// Live Google preview in the search-engine card (same fallbacks as the server).
const seoTexts = computed(() => Object.fromEntries(Object.entries(form.translations).map(([l, f]) => [l, { title: f.name, description: f.description.replace(/<[^>]*>/g, ' ') }])));

function submit(): void {
    // POST + _method so the logo upload works for updates too.
    const url = props.brand ? route('admin.brands.update', props.brand.id) : route('admin.brands.store');
    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onError: () => void focusFirstError(),
        onSuccess: () => {
            form.logo = null;
            form.defaults();
        },
    });
}
</script>

<template>
    <form id="brand-form" novalidate @submit.prevent="submit">
        <FormToolbar
            :title="brand ? form.translations.ar.name || t('common.edit') : t('admin.new_brand')"
            :back-href="route('admin.brands.index')"
            form-id="brand-form"
            :dirty="form.isDirty"
            :processing="form.processing"
            @discard="form.reset()"
        />
        <ErrorSummary class="mb-5" />

        <div class="grid items-start gap-5 lg:gap-7.5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <FormCard :title="t('admin.content')" :description="t('admin.translations_help')" icon="document" :errors="['translations']">
                <LocaleTabs>
                    <template #default="{ locale }">
                        <div class="grid gap-5">
                            <FormField :id="`n-${locale}`" :label="t('admin.name')" :error-key="`translations.${locale}.name`" :context="names[locale]" :required="locale === 'ar'">
                                <input :id="`n-${locale}`" v-model="form.translations[locale].name" class="kt-input kt-input-lg text-base" maxlength="255" />
                            </FormField>
                            <FormField :id="`s-${locale}`" :label="t('forms.product.url')" :help="t('forms.product.url_help')" :error-key="`translations.${locale}.slug`" :context="names[locale]">
                                <div class="kt-input-group" dir="ltr">
                                    <span class="kt-input-addon text-xs">/{{ locale }}/brand/</span>
                                    <input :id="`s-${locale}`" v-model="form.translations[locale].slug" class="kt-input font-mono text-xs" maxlength="160" />
                                </div>
                            </FormField>
                            <FormField :id="`d-${locale}`" :label="t('admin.description')" :error-key="`translations.${locale}.description`" :context="names[locale]" optional>
                                <RichTextEditor :id="`d-${locale}`" v-model="form.translations[locale].description" />
                            </FormField>
                        </div>
                    </template>
                </LocaleTabs>
            </FormCard>

            <FormCard :title="t('admin.general')" icon="setting-2" :errors="['logo', 'sort_order', 'is_active', 'is_featured']">
                <div class="grid gap-5">
                    <FormField id="logo" :label="t('admin.logo')" error-key="logo">
                        <ImageInput id="logo" v-model="form.logo" :current="brand?.logo" fit="contain" />
                    </FormField>
                    <SwitchRow id="is_active" v-model="form.is_active" :label="t('admin.active')" />
                    <SwitchRow id="is_featured" v-model="form.is_featured" :label="t('admin.featured')" />
                    <FormField id="sort" :label="t('admin.sort_order')" error-key="sort_order">
                        <input id="sort" v-model.number="form.sort_order" type="number" min="0" class="kt-input" dir="ltr" />
                    </FormField>
                </div>
            </FormCard>
        </div>
    </form>
    <SeoPanel v-if="seo_overrides" class="mt-5 lg:mt-7.5" :data="seo_overrides" :texts="seoTexts" />
</template>
