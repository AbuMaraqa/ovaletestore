<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

type Localized = Record<string, string>;
const props = defineProps<{ group: 'general' | 'contact' | 'store' | 'seo'; groups: string[]; values: Record<string, any>; locales: string[] }>();
const { t } = useTrans();

/** File inputs are separate keys (logo/favicon/og_image); existing URLs are display-only. */
const localized = (key: string): Localized => Object.fromEntries(props.locales.map((l) => [l, (props.values[key] as Localized | undefined)?.[l] ?? '']));
const initial: Record<string, any> = {
    general: { site_name: localized('site_name'), tagline: localized('tagline'), logo: null, favicon: null },
    contact: { phone: props.values.phone ?? '', whatsapp: props.values.whatsapp ?? '', email: props.values.email ?? '', address: localized('address'), social_links: { facebook: '', instagram: '', snapchat: '', tiktok: '', telegram: '', ...(props.values.social_links ?? {}) } },
    store: { ...props.values },
    seo: { home_title: localized('home_title'), home_description: localized('home_description'), og_image: null, allow_indexing: props.values.allow_indexing },
}[props.group];

const form = useForm<Record<string, any>>({ ...initial, _method: 'put' });
const errors = computed(() => form.errors as Record<string, string>);
const submit = () => form.post(route('admin.settings.update', props.group), { forceFormData: true, preserveScroll: true });
const setFile = (key: string, e: Event) => ((form as any)[key] = (e.target as HTMLInputElement).files?.[0] ?? null);
const dirFor = (l: string) => (l === 'en' ? 'ltr' : 'rtl');
</script>

<template>
    <PageHeader :title="t('admin.settings')" />

    <div class="mb-6 flex gap-1 border-b border-border">
        <Link
            v-for="g in groups"
            :key="g"
            :href="route('admin.settings.edit', g)"
            :class="['px-4 py-2.5 text-sm font-medium', g === group ? 'border-b-2 border-primary text-primary' : 'text-muted-foreground hover:text-foreground']"
        >
            {{ t(`admin.${g}`) }}
        </Link>
    </div>

    <form class="max-w-4xl" @submit.prevent="submit">
        <KtCard :title="t(`admin.${group}`)">
            <div class="grid gap-5">
                <template v-if="group === 'general'">
                    <div class="grid gap-5 md:grid-cols-3">
                        <FormField v-for="l in locales" :id="`sn-${l}`" :key="l" :label="`${t('admin.site_name')} (${l})`" :error="errors[`site_name.${l}`]" :required="l === 'ar'">
                            <input :id="`sn-${l}`" v-model="form.site_name[l]" class="kt-input" :dir="dirFor(l)" />
                        </FormField>
                        <FormField v-for="l in locales" :id="`tg-${l}`" :key="`tg${l}`" :label="`${t('admin.tagline')} (${l})`" :error="errors[`tagline.${l}`]">
                            <input :id="`tg-${l}`" v-model="form.tagline[l]" class="kt-input" :dir="dirFor(l)" />
                        </FormField>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <FormField id="logo" :label="t('admin.logo')" :error="errors.logo">
                            <img v-if="values.logo" :src="values.logo" alt="" class="mb-2 h-14 rounded bg-white object-contain" />
                            <input id="logo" type="file" accept="image/png,image/webp,image/jpeg" class="kt-input" @change="setFile('logo', $event)" />
                        </FormField>
                        <FormField id="favicon" :label="t('admin.favicon')" :error="errors.favicon">
                            <img v-if="values.favicon" :src="values.favicon" alt="" class="mb-2 size-8" />
                            <input id="favicon" type="file" accept="image/png,image/x-icon" class="kt-input" @change="setFile('favicon', $event)" />
                        </FormField>
                    </div>
                </template>

                <template v-else-if="group === 'contact'">
                    <div class="grid gap-5 md:grid-cols-3">
                        <FormField id="phone" :label="t('admin.phone')" :error="errors.phone"><input id="phone" v-model="form.phone" class="kt-input" dir="ltr" /></FormField>
                        <FormField id="whatsapp" :label="t('admin.whatsapp')" help="970599000000" :error="errors.whatsapp"><input id="whatsapp" v-model="form.whatsapp" class="kt-input" dir="ltr" inputmode="numeric" /></FormField>
                        <FormField id="email" :label="t('common.email')" :error="errors.email"><input id="email" v-model="form.email" type="email" class="kt-input" dir="ltr" /></FormField>
                        <FormField v-for="l in locales" :id="`ad-${l}`" :key="l" :label="`${t('admin.address')} (${l})`" :error="errors[`address.${l}`]">
                            <input :id="`ad-${l}`" v-model="form.address[l]" class="kt-input" :dir="dirFor(l)" />
                        </FormField>
                        <FormField v-for="net in ['facebook', 'instagram', 'snapchat', 'tiktok', 'telegram']" :id="`sl-${net}`" :key="net" :label="net" :error="errors[`social_links.${net}`]">
                            <input :id="`sl-${net}`" v-model="form.social_links[net]" type="url" class="kt-input" dir="ltr" placeholder="https://" />
                        </FormField>
                    </div>
                </template>

                <template v-else-if="group === 'store'">
                    <div class="grid gap-5 md:grid-cols-3">
                        <FormField id="currency" :label="t('admin.currency')" :error="errors.currency">
                            <select id="currency" v-model="form.currency" class="kt-select"><option v-for="c in ['ILS', 'USD', 'JOD']" :key="c" :value="c">{{ c }}</option></select>
                        </FormField>
                        <FormField id="ppp" :label="t('admin.products_per_page')" :error="errors.products_per_page"><input id="ppp" v-model.number="form.products_per_page" type="number" min="8" max="96" class="kt-input" /></FormField>
                        <FormField id="min" :label="t('admin.min_order_total')" :error="errors.min_order_total"><input id="min" v-model="form.min_order_total" type="number" step="0.01" min="0" class="kt-input" dir="ltr" /></FormField>
                        <FormField id="ship" :label="t('admin.shipping_fee')" :error="errors.shipping_fee"><input id="ship" v-model="form.shipping_fee" type="number" step="0.01" min="0" class="kt-input" dir="ltr" /></FormField>
                    </div>
                    <label class="flex items-center gap-3 text-sm"><input v-model="form.show_out_of_stock" type="checkbox" class="kt-switch" /> {{ t('admin.show_out_of_stock') }}</label>
                    <label class="flex items-center gap-3 text-sm"><input v-model="form.guest_checkout" type="checkbox" class="kt-switch" /> {{ t('admin.guest_checkout') }}</label>
                </template>

                <template v-else>
                    <FormField v-for="l in locales" :id="`ht-${l}`" :key="l" :label="`${t('admin.home_title')} (${l})`" :error="errors[`home_title.${l}`]" :required="l === 'ar'">
                        <input :id="`ht-${l}`" v-model="form.home_title[l]" class="kt-input" maxlength="70" :dir="dirFor(l)" />
                    </FormField>
                    <FormField v-for="l in locales" :id="`hd-${l}`" :key="`hd${l}`" :label="`${t('admin.home_description')} (${l})`" :error="errors[`home_description.${l}`]">
                        <textarea :id="`hd-${l}`" v-model="form.home_description[l]" class="kt-textarea" rows="2" maxlength="170" :dir="dirFor(l)" />
                    </FormField>
                    <FormField id="og" :label="t('admin.og_image')" help="1200×630" :error="errors.og_image">
                        <img v-if="values.og_image" :src="values.og_image" alt="" class="mb-2 h-24 rounded object-cover" />
                        <input id="og" type="file" accept="image/jpeg,image/png,image/webp" class="kt-input" @change="setFile('og_image', $event)" />
                    </FormField>
                    <label class="flex items-center gap-3 text-sm"><input v-model="form.allow_indexing" type="checkbox" class="kt-switch" /> {{ t('admin.allow_indexing') }}</label>
                </template>
            </div>
            <template #footer><KtButton type="submit" :loading="form.processing" icon="check" class="ms-auto">{{ t('common.save') }}</KtButton></template>
        </KtCard>
    </form>
</template>
