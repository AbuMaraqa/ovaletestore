<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AuthCard from '@/Components/Storefront/AuthCard.vue';
import StoreField from '@/Components/Storefront/StoreField.vue';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps<{ honeypot?: { enabled: boolean; nameFieldName: string; validFromFieldName: string; encryptedValidFrom: string } }>();
const { t } = useTrans();

const form = useForm<Record<string, string>>({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    trader_request: '',
    business_name: '',
    ...(props.honeypot?.enabled ? { [props.honeypot.nameFieldName]: '', [props.honeypot.validFromFieldName]: props.honeypot.encryptedValidFrom } : {}),
});
const submit = () => form.post(route('register.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <AuthCard :title="t('common.register_title')" :subtitle="t('common.register_subtitle')">
        <form class="space-y-5" novalidate @submit.prevent="submit">
            <StoreField id="name" v-model="form.name" :label="t('cart.name')" :error="form.errors.name" autocomplete="name" required autofocus />
            <div class="grid gap-5 sm:grid-cols-2">
                <StoreField id="email" v-model="form.email" type="email" :label="t('common.email')" :error="form.errors.email" autocomplete="email" dir="ltr" required />
                <StoreField id="phone" v-model="form.phone" type="tel" :label="t('cart.phone')" :error="form.errors.phone" autocomplete="tel" inputmode="tel" dir="ltr" required />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <StoreField id="password" v-model="form.password" type="password" :label="t('common.password')" :error="form.errors.password" autocomplete="new-password" required />
                <StoreField id="password_confirmation" v-model="form.password_confirmation" type="password" :label="t('common.confirm_password')" autocomplete="new-password" required />
            </div>
            <!-- Optional trader (wholesale) request: reviewed by the shop before trader prices apply. -->
            <div class="rounded-2xl bg-cream p-4 ring-1 ring-stone-200/70">
                <label class="flex cursor-pointer items-start gap-3">
                    <input v-model="form.trader_request" type="checkbox" true-value="1" false-value="" class="mt-1 size-4 accent-brand-600" />
                    <span>
                        <span class="block text-sm font-bold text-cocoa">{{ t('storefront.trader_signup') }}</span>
                        <span class="block text-xs text-stone-600">{{ t('storefront.trader_signup_help') }}</span>
                    </span>
                </label>
                <StoreField
                    v-if="form.trader_request"
                    id="business_name"
                    v-model="form.business_name"
                    class="mt-4"
                    :label="t('storefront.business_name')"
                    :error="form.errors.business_name"
                    autocomplete="organization"
                    required
                />
            </div>
            <div v-if="honeypot?.enabled" class="hidden" aria-hidden="true">
                <input v-model="form[honeypot.nameFieldName]" type="text" tabindex="-1" autocomplete="off" />
            </div>
            <button type="submit" class="btn-primary h-12 w-full text-base" :disabled="form.processing">{{ t('common.register') }}</button>
        </form>
        <p class="mt-8 text-center text-sm text-stone-500">
            {{ t('common.have_account') }}
            <Link :href="route('login')" class="font-bold text-brand-700 hover:underline">{{ t('common.login') }}</Link>
        </p>
    </AuthCard>
</template>
