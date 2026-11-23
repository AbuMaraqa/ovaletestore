<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AuthCard from '@/Components/Storefront/AuthCard.vue';
import StoreField from '@/Components/Storefront/StoreField.vue';
import { useTrans } from '@/Composables/useTrans';

defineProps<{ status?: string | null }>();
const { t } = useTrans();
const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post(route('login.store'), { onFinish: () => form.reset('password') });
</script>

<template>
    <AuthCard :title="t('common.login_title')" :subtitle="t('common.login_subtitle')">
        <p v-if="status" class="mb-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ status }}</p>
        <form class="space-y-5" novalidate @submit.prevent="submit">
            <StoreField id="email" v-model="form.email" type="email" :label="t('common.email')" :error="form.errors.email" autocomplete="username" dir="ltr" required autofocus />
            <StoreField id="password" v-model="form.password" type="password" :label="t('common.password')" :error="form.errors.password" autocomplete="current-password" required />
            <div class="flex items-center justify-between text-sm">
                <label class="inline-flex items-center gap-2">
                    <input v-model="form.remember" type="checkbox" class="size-4 rounded border-stone-300 text-brand-600 focus:ring-brand-500" />
                    {{ t('common.remember_me') }}
                </label>
                <Link :href="route('password.request')" class="font-semibold text-brand-700 hover:underline">{{ t('common.forgot_password') }}</Link>
            </div>
            <button type="submit" class="btn-primary h-12 w-full text-base" :disabled="form.processing">{{ t('common.login') }}</button>
        </form>
        <p class="mt-8 text-center text-sm text-stone-500">
            {{ t('common.no_account') }}
            <Link :href="route('register')" class="font-bold text-brand-700 hover:underline">{{ t('common.register') }}</Link>
        </p>
    </AuthCard>
</template>
