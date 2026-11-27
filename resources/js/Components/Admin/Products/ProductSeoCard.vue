<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import SerpPreview from '@/Components/Admin/Seo/SerpPreview.vue';
import FormCard from '@/Components/Forms/FormCard.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import LocaleTabs from '@/Components/Forms/LocaleTabs.vue';
import { useStagedUploads } from '@/Composables/useStagedUploads';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';
import type { Locale, ProductSeoForm, ProductTranslationForm } from '@/types/admin';

/**
 * Search engines, inside the product form (one Save): per language the URL slug, meta title and description with a
 * live Google preview; then the laravel-seo overrides — robots, canonical URL and a share image (staged upload).
 */
const translations = defineModel<Record<Locale, ProductTranslationForm>>('translations', { required: true });
const seo = defineModel<ProductSeoForm>('seo', { required: true });
const locale = defineModel<string>('locale', { required: true });
const props = defineProps<{ locales: string[]; urls?: Record<string, string>; image?: string | null; robotsOptions: string[] }>();
const { t } = useTrans();
const siteName = usePage<SharedProps>().props.site.name;
const names: Record<string, string> = { ar: 'العربية', en: 'English', he: 'עברית' };

const strip = (html: string) => html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
const slugify = (v: string) => v.trim().toLowerCase().replace(/[^\p{L}\p{M}\p{N}]+/gu, '-').replace(/^-+|-+$/g, '');
const urlFor = (l: string) => {
    const tr = translations.value[l as Locale];
    const slug = tr.slug || slugify(tr.name || translations.value.ar.name);
    return slug ? `${window.location.origin}/${l}/product/${slug}` : (props.urls?.[l] ?? `${window.location.origin}/${l}/product/…`);
};
const preview = (l: string) => {
    const tr = translations.value[l as Locale];
    return { title: tr.meta_title || tr.name || translations.value.ar.name, description: tr.meta_description || strip(tr.short_description || tr.description) };
};
const errorKeys = computed(() => [...props.locales.flatMap((l) => ['slug', 'meta_title', 'meta_description'].map((f) => `translations.${l}.${f}`)), 'seo']);

const share = useStagedUploads((ids) => (seo.value.image_upload = ids[0] ?? null), () => 1);
watch(
    () => seo.value.image_upload,
    (id) => id === null && share.items.length && share.reset(),
);
</script>

<template>
    <FormCard :title="t('forms.product.seo')" :description="t('forms.product.seo_help')" icon="magnifier" :errors="errorKeys" collapsible :open="false">
        <LocaleTabs v-model:active="locale" :locales="locales" prefix="translations">
            <template #default="{ locale: l }">
                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="grid content-start gap-5">
                        <FormField :id="`slug-${l}`" :label="t('forms.product.url')" :help="t('forms.product.url_help')" :error-key="`translations.${l}.slug`" :context="names[l]">
                            <div class="kt-input-group" dir="ltr">
                                <span class="kt-input-addon text-xs">/{{ l }}/product/</span>
                                <input :id="`slug-${l}`" v-model="translations[l as Locale].slug" class="kt-input font-mono text-xs" maxlength="160" />
                            </div>
                        </FormField>
                        <FormField
                            :id="`mt-${l}`"
                            :label="t('admin.meta_title')"
                            :error-key="`translations.${l}.meta_title`"
                            :context="names[l]"
                            :counter="{ value: translations[l as Locale].meta_title.length, max: 60 }"
                            optional
                        >
                            <input :id="`mt-${l}`" v-model="translations[l as Locale].meta_title" class="kt-input" maxlength="70" :placeholder="translations[l as Locale].name" />
                        </FormField>
                        <FormField
                            :id="`md-${l}`"
                            :label="t('admin.meta_description')"
                            :error-key="`translations.${l}.meta_description`"
                            :context="names[l]"
                            :counter="{ value: translations[l as Locale].meta_description.length, max: 160 }"
                            optional
                        >
                            <textarea :id="`md-${l}`" v-model="translations[l as Locale].meta_description" class="kt-textarea" rows="3" maxlength="170" />
                        </FormField>
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-medium text-foreground">{{ t('seo.preview') }}</p>
                        <SerpPreview v-bind="preview(l)" :url="urlFor(l)" :site-name="siteName" :dir="l === 'en' ? 'ltr' : 'rtl'" />
                    </div>
                </div>
            </template>
        </LocaleTabs>

        <FormSection :title="t('forms.product.advanced')" class="mt-6 border-t border-border pt-6">
            <div class="grid gap-5 md:grid-cols-2">
                <FormField id="seo-robots" :label="t('seo.robots')" :help="t('seo.robots_help')" error-key="seo.robots">
                    <select id="seo-robots" v-model="seo.robots" class="kt-select">
                        <option :value="null">{{ t('seo.robots_default') }}</option>
                        <option v-for="r in robotsOptions" :key="r" :value="r">{{ t(`seo.robots_options.${r}`) }}</option>
                    </select>
                </FormField>
                <FormField id="seo-canonical" :label="t('seo.canonical_url')" :help="t('seo.canonical_help')" error-key="seo.canonical_url" optional>
                    <input id="seo-canonical" v-model="seo.canonical_url" type="url" class="kt-input" dir="ltr" placeholder="https://" />
                </FormField>
                <FormField id="seo-image" :label="t('seo.share_image')" :help="t('seo.share_image_help')" error-key="seo.image_upload" class="md:col-span-2">
                    <div class="flex flex-wrap items-center gap-4">
                        <img
                            v-if="share.items[0]?.preview || (image && !seo.remove_image)"
                            :src="share.items[0]?.preview ?? image ?? ''"
                            alt=""
                            class="aspect-[1.91/1] w-40 rounded-lg border border-border object-cover"
                        />
                        <label class="kt-btn kt-btn-outline cursor-pointer">
                            {{ t('admin.upload_images') }}
                            <input id="seo-image" type="file" class="sr-only" accept="image/jpeg,image/png,image/webp" @change="($event.target as HTMLInputElement).files && (share.reset(), share.add(($event.target as HTMLInputElement).files!))" />
                        </label>
                        <span v-if="share.items[0]?.status === 'uploading'" class="text-xs text-muted-foreground">{{ share.items[0].progress }}%</span>
                        <span v-if="share.items[0]?.status === 'error'" class="form-field-error">{{ share.items[0].error }}</span>
                        <label v-if="image && !share.items.length" class="flex items-center gap-2 text-sm">
                            <input v-model="seo.remove_image" type="checkbox" class="kt-checkbox kt-checkbox-sm" /> {{ t('seo.remove_image') }}
                        </label>
                    </div>
                </FormField>
            </div>
        </FormSection>
    </FormCard>
</template>
