<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import SerpPreview from '@/Components/Admin/Seo/SerpPreview.vue';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

export interface SeoOverrides {
    update_url: string;
    robots: string | null;
    canonical_url: string | null;
    image: string | null;
    robots_options: string[];
    urls: Record<string, string>;
}

/**
 * Search-engine card of a product / category / brand (laravel-seo `seo` row): Google preview per language
 * (live from the parent form's title/description), robots, canonical URL and share image. Saves on its own.
 */
const props = defineProps<{ data: SeoOverrides; texts: Record<string, { title: string; description: string }> }>();
const { t } = useTrans();
const siteName = usePage<SharedProps>().props.site.name;

const locales = computed(() => Object.keys(props.data.urls));
const locale = ref(locales.value[0] ?? 'ar');
const form = useForm({
    robots: props.data.robots,
    canonical_url: props.data.canonical_url ?? '',
    image: null as File | null,
    remove_image: false,
    _method: 'put',
});
const errors = computed(() => form.errors as Record<string, string>);
const fileInput = ref<HTMLInputElement | null>(null);

function save(): void {
    form.post(props.data.update_url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('image', 'remove_image');
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
</script>

<template>
    <KtCard :title="t('seo.panel_title')" :description="t('seo.panel_help')">
        <form class="grid gap-6 lg:grid-cols-2" @submit.prevent="save">
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-medium text-mono">{{ t('seo.preview') }}</p>
                    <div class="flex gap-1" role="tablist">
                        <button
                            v-for="l in locales"
                            :key="l"
                            type="button"
                            role="tab"
                            :aria-selected="l === locale"
                            :class="['kt-btn kt-btn-sm uppercase', l === locale ? 'kt-btn-primary' : 'kt-btn-outline']"
                            @click="locale = l"
                        >
                            {{ l }}
                        </button>
                    </div>
                </div>
                <SerpPreview
                    :title="texts[locale]?.title ?? ''"
                    :description="texts[locale]?.description ?? ''"
                    :url="data.urls[locale]"
                    :site-name="siteName"
                    :dir="locale === 'en' ? 'ltr' : 'rtl'"
                />
            </div>

            <div class="space-y-4">
                <FormField id="seo-robots" :label="t('seo.robots')" :help="t('seo.robots_help')" :error="errors.robots">
                    <select id="seo-robots" v-model="form.robots" class="kt-select">
                        <option :value="null">{{ t('seo.robots_default') }}</option>
                        <option v-for="r in data.robots_options" :key="r" :value="r">{{ t(`seo.robots_options.${r}`) }}</option>
                    </select>
                </FormField>
                <FormField id="seo-canonical" :label="t('seo.canonical_url')" :help="t('seo.canonical_help')" :error="errors.canonical_url">
                    <input id="seo-canonical" v-model="form.canonical_url" type="url" class="kt-input" dir="ltr" placeholder="https://" />
                </FormField>
                <FormField id="seo-image" :label="t('seo.share_image')" :help="t('seo.share_image_help')" :error="errors.image">
                    <img v-if="data.image && !form.remove_image" :src="data.image" alt="" class="mb-2 aspect-[1.91/1] w-48 rounded-lg object-cover" />
                    <input
                        id="seo-image"
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="kt-input"
                        @change="form.image = ($event.target as HTMLInputElement).files?.[0] ?? null"
                    />
                    <label v-if="data.image" class="mt-2 flex items-center gap-2 text-sm">
                        <input v-model="form.remove_image" type="checkbox" class="kt-checkbox" /> {{ t('seo.remove_image') }}
                    </label>
                </FormField>
                <div class="flex justify-end">
                    <KtButton type="submit" :loading="form.processing" icon="check">{{ t('common.save') }}</KtButton>
                </div>
            </div>
        </form>
    </KtCard>
</template>
