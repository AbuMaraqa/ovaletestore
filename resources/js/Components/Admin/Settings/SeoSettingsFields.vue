<script setup lang="ts">
import FormField from '@/Components/Forms/FormField.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/**
 * Settings → SEO: homepage text, default description (laravel-seo fallback), share image, indexing switch,
 * X account + webmaster verification codes, the GA4 Measurement ID and links to the sitemap / robots.txt.
 */
const form = defineModel<Record<string, any>>({ required: true });
defineProps<{ errors: Record<string, string>; values: Record<string, any>; locales: string[] }>();
const emit = defineEmits<{ file: [key: string, event: Event] }>();
const { t } = useTrans();
const dirFor = (l: string) => (l === 'en' ? 'ltr' : 'rtl');
</script>

<template>
    <FormField v-for="l in locales" :id="`ht-${l}`" :key="l" :label="`${t('admin.home_title')} (${l})`" :error-key="`home_title.${l}`" :required="l === 'ar'">
        <input :id="`ht-${l}`" v-model="form.home_title[l]" class="kt-input" maxlength="70" :dir="dirFor(l)" />
    </FormField>
    <FormField v-for="l in locales" :id="`hd-${l}`" :key="`hd${l}`" :label="`${t('admin.home_description')} (${l})`" :error-key="`home_description.${l}`">
        <textarea :id="`hd-${l}`" v-model="form.home_description[l]" class="kt-textarea" rows="2" maxlength="170" :dir="dirFor(l)" />
    </FormField>
    <FormField
        v-for="l in locales"
        :id="`dd-${l}`"
        :key="`dd${l}`"
        :label="`${t('seo.default_description')} (${l})`"
        :help="t('seo.default_description_help')"
        :error-key="`default_description.${l}`"
    >
        <textarea :id="`dd-${l}`" v-model="form.default_description[l]" class="kt-textarea" rows="2" maxlength="170" :dir="dirFor(l)" />
    </FormField>
    <FormField id="og" :label="t('admin.og_image')" help="1200×630" error-key="og_image">
        <img v-if="values.og_image" :src="values.og_image" alt="" class="mb-2 h-24 rounded object-cover" />
        <input id="og" type="file" accept="image/jpeg,image/png,image/webp" class="kt-input" @change="emit('file', 'og_image', $event)" />
    </FormField>
    <label class="flex items-center gap-3 text-sm"><input v-model="form.allow_indexing" type="checkbox" class="kt-switch" /> {{ t('admin.allow_indexing') }}</label>

    <h3 class="border-t border-border pt-5 text-sm font-semibold text-mono">{{ t('seo.social') }} · {{ t('seo.verification') }}</h3>
    <p class="-mt-3 text-xs text-muted-foreground">{{ t('seo.verification_help') }}</p>
    <div class="grid gap-5 md:grid-cols-3">
        <FormField id="tw" :label="t('seo.twitter_username')" error-key="twitter_username">
            <input id="tw" v-model="form.twitter_username" class="kt-input" dir="ltr" maxlength="16" placeholder="@ovalete" />
        </FormField>
        <FormField id="gv" :label="t('seo.google_verification')" error-key="google_verification">
            <input id="gv" v-model="form.google_verification" class="kt-input font-mono text-xs" dir="ltr" maxlength="100" />
        </FormField>
        <FormField id="bv" :label="t('seo.bing_verification')" error-key="bing_verification">
            <input id="bv" v-model="form.bing_verification" class="kt-input font-mono text-xs" dir="ltr" maxlength="100" />
        </FormField>
    </div>

    <h3 class="border-t border-border pt-5 text-sm font-semibold text-mono">{{ t('seo.analytics') }}</h3>
    <FormField id="ga" :label="t('seo.ga_measurement_id')" :help="t('seo.ga_measurement_id_help')" error-key="ga_measurement_id">
        <input id="ga" v-model="form.ga_measurement_id" class="kt-input max-w-xs font-mono" dir="ltr" placeholder="G-XXXXXXXXXX" />
    </FormField>

    <h3 class="border-t border-border pt-5 text-sm font-semibold text-mono">{{ t('seo.files') }}</h3>
    <p class="-mt-3 text-xs text-muted-foreground">{{ t('seo.sitemap_help') }}</p>
    <div class="flex flex-wrap gap-2">
        <!-- Plain links (not Inertia visits): XML / text files open in a new tab. -->
        <a :href="values.sitemap_url" target="_blank" rel="noopener" class="kt-btn kt-btn-outline"><KtIcon name="map" /> {{ t('seo.sitemap') }}</a>
        <a :href="values.robots_url" target="_blank" rel="noopener" class="kt-btn kt-btn-outline"><KtIcon name="code" /> {{ t('seo.robots_txt') }}</a>
    </div>
</template>
