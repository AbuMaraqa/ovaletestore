<script setup lang="ts">
import FormField from '@/Components/Forms/FormField.vue';
import ImageInput from '@/Components/Forms/ImageInput.vue';
import { useTrans } from '@/Composables/useTrans';

/** Settings → Homepage: the hero photo (large preview, replaced on save) and the hero headline / text per language. */
const form = defineModel<Record<string, any>>({ required: true });
defineProps<{ values: Record<string, any>; locales: string[] }>();
const { t } = useTrans();

const names: Record<string, string> = { ar: 'العربية', en: 'English', he: 'עברית' };
const dirFor = (l: string) => (l === 'en' ? 'ltr' : 'rtl');
</script>

<template>
    <FormField id="hero_image" :label="t('admin.hero_image')" :help="t('admin.hero_image_help')" error-key="hero_image">
        <ImageInput id="hero_image" v-model="form.hero_image" :current="values.hero_image" accept="image/jpeg,image/png,image/webp" shape="banner" />
    </FormField>

    <div class="grid gap-5 border-t border-border pt-5 md:grid-cols-3">
        <div v-for="l in locales" :key="l" class="grid content-start gap-4">
            <FormField :id="`hero-title-${l}`" :label="`${t('admin.hero_title')} (${names[l] ?? l})`" :error-key="`hero_title.${l}`" :required="l === 'ar'">
                <input :id="`hero-title-${l}`" v-model="form.hero_title[l]" class="kt-input" maxlength="120" :dir="dirFor(l)" />
            </FormField>
            <FormField :id="`hero-sub-${l}`" :label="`${t('admin.hero_subtitle')} (${names[l] ?? l})`" :error-key="`hero_subtitle.${l}`">
                <textarea :id="`hero-sub-${l}`" v-model="form.hero_subtitle[l]" class="kt-textarea" rows="3" maxlength="300" :dir="dirFor(l)" />
            </FormField>
        </div>
    </div>
</template>
