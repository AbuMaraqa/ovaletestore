<script setup lang="ts">
import FormField from '@/Components/Forms/FormField.vue';
import LocaleTabs from '@/Components/Forms/LocaleTabs.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import { useTrans } from '@/Composables/useTrans';
import type { Locale, ProductTranslationForm } from '@/types/admin';

const model = defineModel<Record<Locale, ProductTranslationForm>>({ required: true });
defineProps<{ errors: Record<string, string>; locales: string[] }>();
const { t } = useTrans();
const key = (locale: string, field: string) => `translations.${locale}.${field}`;
</script>

<template>
    <KtCard :title="t('admin.content')" :description="t('admin.translations_help')">
        <LocaleTabs :locales="locales" :errors="errors">
            <template #default="{ locale }">
                <div class="grid gap-5">
                    <FormField :id="`name-${locale}`" :label="t('admin.name')" :error="errors[key(locale, 'name')]" :required="locale === 'ar'">
                        <input :id="`name-${locale}`" v-model="model[locale as Locale].name" class="kt-input" maxlength="255" :aria-invalid="!!errors[key(locale, 'name')]" />
                    </FormField>
                    <FormField :id="`slug-${locale}`" :label="t('admin.slug')" :help="t('admin.slug_help')" :error="errors[key(locale, 'slug')]">
                        <input :id="`slug-${locale}`" v-model="model[locale as Locale].slug" class="kt-input font-mono text-xs" maxlength="160" dir="auto" />
                    </FormField>
                    <FormField :id="`short-${locale}`" :label="t('admin.short_description')" :error="errors[key(locale, 'short_description')]">
                        <textarea :id="`short-${locale}`" v-model="model[locale as Locale].short_description" class="kt-textarea" rows="3" />
                    </FormField>
                    <FormField :id="`desc-${locale}`" :label="t('admin.description')" :error="errors[key(locale, 'description')]">
                        <textarea :id="`desc-${locale}`" v-model="model[locale as Locale].description" class="kt-textarea font-mono text-xs" rows="10" />
                    </FormField>
                    <div class="grid gap-5 rounded-lg border border-dashed border-border p-4 md:grid-cols-2">
                        <p class="text-sm font-medium text-mono md:col-span-2">{{ t('admin.seo') }}</p>
                        <FormField :id="`mt-${locale}`" :label="t('admin.meta_title')" :error="errors[key(locale, 'meta_title')]">
                            <input :id="`mt-${locale}`" v-model="model[locale as Locale].meta_title" class="kt-input" maxlength="70" />
                        </FormField>
                        <FormField :id="`md-${locale}`" :label="t('admin.meta_description')" :error="errors[key(locale, 'meta_description')]">
                            <textarea :id="`md-${locale}`" v-model="model[locale as Locale].meta_description" class="kt-textarea" rows="2" maxlength="170" />
                        </FormField>
                    </div>
                </div>
            </template>
        </LocaleTabs>
    </KtCard>
</template>
