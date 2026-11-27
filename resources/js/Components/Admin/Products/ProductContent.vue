<script setup lang="ts">
import FormCard from '@/Components/Forms/FormCard.vue';
import FormField from '@/Components/Forms/FormField.vue';
import LocaleTabs from '@/Components/Forms/LocaleTabs.vue';
import RichTextEditor from '@/Components/Forms/RichTextEditor.vue';
import { useTrans } from '@/Composables/useTrans';
import type { Locale, ProductTranslationForm } from '@/types/admin';

/** "Basic information": name, short description and description per language (rich text, sanitised on save). */
const model = defineModel<Record<Locale, ProductTranslationForm>>({ required: true });
const locale = defineModel<string>('locale', { required: true });
defineProps<{ locales: string[] }>();
const { t } = useTrans();

const names: Record<string, string> = { ar: 'العربية', en: 'English', he: 'עברית' };
const fields = (l: string) => ['name', 'short_description', 'description'].map((f) => `translations.${l}.${f}`);
</script>

<template>
    <FormCard :title="t('forms.product.basic_info')" :description="t('forms.product.basic_info_help')" icon="document" :errors="locales.flatMap(fields)">
        <LocaleTabs v-model:active="locale" :locales="locales">
            <template #default="{ locale: l }">
                <div class="grid gap-5">
                    <FormField :id="`name-${l}`" :label="t('admin.name')" :error-key="`translations.${l}.name`" :context="names[l]" :required="l === 'ar'">
                        <input
                            :id="`name-${l}`"
                            v-model="model[l as Locale].name"
                            class="kt-input kt-input-lg text-base"
                            maxlength="255"
                            :placeholder="t('forms.product.name_placeholder')"
                            :autofocus="l === 'ar' && !model.ar.name"
                        />
                    </FormField>
                    <FormField
                        :id="`short-${l}`"
                        :label="t('forms.product.short_description')"
                        :help="t('forms.product.short_description_help')"
                        :error-key="`translations.${l}.short_description`"
                        :context="names[l]"
                        optional
                    >
                        <RichTextEditor :id="`short-${l}`" v-model="model[l as Locale].short_description" min-height="sm" />
                    </FormField>
                    <FormField :id="`desc-${l}`" :label="t('admin.description')" :error-key="`translations.${l}.description`" :context="names[l]" optional>
                        <RichTextEditor :id="`desc-${l}`" v-model="model[l as Locale].description" :placeholder="t('forms.product.description_placeholder')" min-height="lg" />
                    </FormField>
                </div>
            </template>
        </LocaleTabs>
    </FormCard>
</template>
