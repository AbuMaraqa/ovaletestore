<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FontSettingsFields from '@/Components/Admin/Settings/FontSettingsFields.vue';
import LocationsEditor from '@/Components/Admin/Settings/LocationsEditor.vue';
import MailSettingsFields from '@/Components/Admin/Settings/MailSettingsFields.vue';
import MailTestCard from '@/Components/Admin/Settings/MailTestCard.vue';
import PhoneLinesEditor from '@/Components/Admin/Settings/PhoneLinesEditor.vue';
import SeoSettingsFields from '@/Components/Admin/Settings/SeoSettingsFields.vue';
import SmtpSettingsFields from '@/Components/Admin/Settings/SmtpSettingsFields.vue';
import ErrorSummary from '@/Components/Forms/ErrorSummary.vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useFormKit } from '@/Composables/useFormKit';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

type Localized = Record<string, string>;
const props = defineProps<{ group: 'general' | 'contact' | 'store' | 'seo' | 'mail' | 'invoice'; groups: string[]; values: Record<string, any>; locales: string[] }>();
const { t } = useTrans();

/** File inputs are separate keys (logo/favicon/og_image); existing URLs are display-only. */
const fill = (values?: Localized): Localized => Object.fromEntries(props.locales.map((l) => [l, values?.[l] ?? '']));
const localized = (key: string): Localized => Object.fromEntries(props.locales.map((l) => [l, (props.values[key] as Localized | undefined)?.[l] ?? '']));
const initial: Record<string, any> = {
    general: {
        site_name: localized('site_name'),
        tagline: localized('tagline'),
        logo: null,
        favicon: null,
        font_family: props.values.font_family ?? 'tajawal',
        font_name: props.values.font_name ?? 'OT Magnit Arabic',
        font_scope: props.values.font_scope ?? 'all',
        font_locales: props.values.font_locales ?? ['ar'],
        font_regular: null,
        font_medium: null,
        font_bold: null,
    },
    contact: {
        phone: props.values.phone ?? '',
        whatsapp: props.values.whatsapp ?? '',
        email: props.values.email ?? '',
        social_links: { facebook: '', instagram: '', snapchat: '', tiktok: '', telegram: '', ...(props.values.social_links ?? {}) },
        phone_lines: (props.values.phone_lines ?? []).map((line: any) => ({ number: line.number, label: fill(line.label) })),
        locations: (props.values.locations ?? []).map((l: any) => ({ name: fill(l.name), address: fill(l.address), coordinates: l.coordinates ?? '' })),
    },
    store: { ...props.values },
    seo: {
        home_title: localized('home_title'),
        home_description: localized('home_description'),
        default_description: localized('default_description'),
        og_image: null,
        allow_indexing: props.values.allow_indexing,
        twitter_username: props.values.twitter_username ?? '',
        google_verification: props.values.google_verification ?? '',
        bing_verification: props.values.bing_verification ?? '',
        ga_measurement_id: props.values.ga_measurement_id ?? '',
    },
    invoice: {
        business_name: props.values.business_name ?? '',
        registration_number: props.values.registration_number ?? '',
        address: props.values.address ?? '',
        phone: props.values.phone ?? '',
        footer_note: localized('footer_note'),
    },
    mail: {
        from_name: props.values.from_name ?? '',
        from_address: props.values.from_address ?? '',
        order_recipients: props.values.order_recipients ?? '',
        notify_new_order: props.values.notify_new_order,
        customer_confirmation: props.values.customer_confirmation,
        customer_status_updates: props.values.customer_status_updates,
        smtp_enabled: props.values.smtp_enabled ?? false,
        smtp_host: props.values.smtp_host ?? '',
        smtp_port: props.values.smtp_port ?? 587,
        smtp_encryption: props.values.smtp_encryption ?? 'tls',
        smtp_username: props.values.smtp_username ?? '',
        smtp_password: '',
        smtp_password_clear: false,
    },
}[props.group];
const siteName = usePage<SharedProps>().props.site.name;

const form = useForm<Record<string, any>>({ ...initial, _method: 'put' });
const errors = computed(() => form.errors as Record<string, string>);
// Form system: errors under each field + a summary that jumps to them, unsaved-changes guard, Ctrl+S.
const { focusFirstError } = useFormKit(form, { submit: () => submit(), guard: true });
const submit = () =>
    form.post(route('admin.settings.update', props.group), {
        forceFormData: true,
        preserveScroll: true,
        onError: () => void focusFirstError(),
        onSuccess: () => form.defaults(),
    });
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

    <form class="max-w-4xl" novalidate @submit.prevent="submit">
        <ErrorSummary class="mb-5" />
        <KtCard :title="t(`admin.${group}`)">
            <div class="grid gap-5">
                <template v-if="group === 'general'">
                    <div class="grid gap-5 md:grid-cols-3">
                        <FormField v-for="l in locales" :id="`sn-${l}`" :key="l" :label="`${t('admin.site_name')} (${l})`" :error-key="`site_name.${l}`" :required="l === 'ar'">
                            <input :id="`sn-${l}`" v-model="form.site_name[l]" class="kt-input" :dir="dirFor(l)" />
                        </FormField>
                        <FormField v-for="l in locales" :id="`tg-${l}`" :key="`tg${l}`" :label="`${t('admin.tagline')} (${l})`" :error-key="`tagline.${l}`">
                            <input :id="`tg-${l}`" v-model="form.tagline[l]" class="kt-input" :dir="dirFor(l)" />
                        </FormField>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <FormField id="logo" :label="t('admin.logo')" error-key="logo">
                            <img v-if="values.logo" :src="values.logo" alt="" class="mb-2 h-14 rounded bg-white object-contain" />
                            <input id="logo" type="file" accept="image/png,image/webp,image/jpeg" class="kt-input" @change="setFile('logo', $event)" />
                        </FormField>
                        <FormField id="favicon" :label="t('admin.favicon')" error-key="favicon">
                            <img v-if="values.favicon" :src="values.favicon" alt="" class="mb-2 size-8" />
                            <input id="favicon" type="file" accept="image/png,image/x-icon" class="kt-input" @change="setFile('favicon', $event)" />
                        </FormField>
                    </div>
                    <!-- Edits the fields of this same form object in place. -->
                    <FontSettingsFields :model-value="form as any" :uploaded="values.font_files ?? {}" :locales="locales" :errors="errors" />
                </template>

                <template v-else-if="group === 'invoice'">
                    <p class="text-sm text-muted-foreground">{{ t('invoices.settings_help') }}</p>
                    <div class="grid gap-5 md:grid-cols-2">
                        <FormField id="business_name" :label="t('invoices.business_name')" error-key="business_name">
                            <input id="business_name" v-model="form.business_name" class="kt-input" maxlength="120" :placeholder="siteName" />
                        </FormField>
                        <FormField id="registration_number" :label="t('invoices.registration_number')" error-key="registration_number">
                            <input id="registration_number" v-model="form.registration_number" class="kt-input" dir="ltr" maxlength="40" />
                        </FormField>
                        <FormField id="inv_address" :label="t('invoices.address')" error-key="address">
                            <input id="inv_address" v-model="form.address" class="kt-input" maxlength="200" />
                        </FormField>
                        <FormField id="inv_phone" :label="t('invoices.phone')" error-key="phone">
                            <input id="inv_phone" v-model="form.phone" type="tel" class="kt-input" dir="ltr" maxlength="32" />
                        </FormField>
                    </div>
                    <div class="grid gap-5 md:grid-cols-3">
                        <FormField v-for="l in locales" :id="`fn-${l}`" :key="l" :label="`${t('invoices.footer_note')} (${l})`" :error-key="`footer_note.${l}`">
                            <textarea :id="`fn-${l}`" v-model="form.footer_note[l]" rows="2" class="kt-textarea" :dir="dirFor(l)" maxlength="300" />
                        </FormField>
                    </div>
                </template>

                <template v-else-if="group === 'contact'">
                    <div class="grid gap-5 md:grid-cols-3">
                        <FormField id="phone" :label="t('admin.phone')" error-key="phone"><input id="phone" v-model="form.phone" class="kt-input" dir="ltr" /></FormField>
                        <FormField id="whatsapp" :label="t('admin.whatsapp')" help="970599000000" error-key="whatsapp"><input id="whatsapp" v-model="form.whatsapp" class="kt-input" dir="ltr" inputmode="numeric" /></FormField>
                        <FormField id="email" :label="t('common.email')" error-key="email"><input id="email" v-model="form.email" type="email" class="kt-input" dir="ltr" /></FormField>
                        <FormField v-for="net in ['facebook', 'instagram', 'snapchat', 'tiktok', 'telegram']" :id="`sl-${net}`" :key="net" :label="net" :error-key="`social_links.${net}`">
                            <input :id="`sl-${net}`" v-model="form.social_links[net]" type="url" class="kt-input" dir="ltr" placeholder="https://" />
                        </FormField>
                    </div>
                    <PhoneLinesEditor v-model="form.phone_lines" :locales="locales" :errors="errors" />
                    <LocationsEditor v-model="form.locations" :locales="locales" :errors="errors" />
                </template>

                <template v-else-if="group === 'store'">
                    <div class="grid gap-5 md:grid-cols-3">
                        <div class="flex items-end">
                            <KtButton :href="route('admin.currencies.index')" variant="ghost" icon="dollar">{{ t('currencies.title') }}</KtButton>
                        </div>
                        <FormField id="ppp" :label="t('admin.products_per_page')" error-key="products_per_page"><input id="ppp" v-model.number="form.products_per_page" type="number" min="8" max="96" class="kt-input" /></FormField>
                        <FormField id="min" :label="t('admin.min_order_total')" error-key="min_order_total"><input id="min" v-model="form.min_order_total" type="number" step="0.01" min="0" class="kt-input" dir="ltr" /></FormField>
                        <div class="flex items-end">
                            <KtButton :href="route('admin.shipping-methods.index')" variant="ghost" icon="delivery">{{ t('admin.shipping_methods') }}</KtButton>
                        </div>
                    </div>
                    <label class="flex items-center gap-3 text-sm"><input v-model="form.show_out_of_stock" type="checkbox" class="kt-switch" /> {{ t('admin.show_out_of_stock') }}</label>
                    <label class="flex items-center gap-3 text-sm"><input v-model="form.guest_checkout" type="checkbox" class="kt-switch" /> {{ t('admin.guest_checkout') }}</label>
                </template>

                <template v-else-if="group === 'mail'">
                    <MailSettingsFields v-model="form" :errors="errors" :site-name="siteName" :default-from="values.transport.default_from" />
                    <SmtpSettingsFields v-model="form" :errors="errors" :password-set="values.smtp_password_set" />
                </template>

                <template v-else>
                    <SeoSettingsFields v-model="form" :errors="errors" :values="values" :locales="locales" @file="setFile" />
                </template>
            </div>
            <template #footer><KtButton type="submit" :loading="form.processing" icon="check" class="ms-auto">{{ t('common.save') }}</KtButton></template>
        </KtCard>
    </form>

    <MailTestCard v-if="group === 'mail'" :transport="values.transport" />
</template>
