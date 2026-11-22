<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import { useTrans } from '@/Composables/useTrans';

defineProps<{ status?: string | null }>();
const { t } = useTrans();
const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post(route('login.store'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head :title="t('common.login')" />
    <h1 class="text-2xl font-semibold text-mono">{{ t('common.login_title') }}</h1>
    <p class="mt-2 text-sm text-muted-foreground">{{ t('common.login_subtitle') }}</p>
    <p v-if="status" class="kt-alert kt-alert-success mt-6 text-sm">{{ status }}</p>

    <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
        <FormField id="email" :label="t('common.email')" :error="form.errors.email">
            <input id="email" v-model="form.email" type="email" class="kt-input" autocomplete="username" required autofocus dir="ltr" />
        </FormField>
        <FormField id="password" :label="t('common.password')" :error="form.errors.password">
            <input id="password" v-model="form.password" type="password" class="kt-input" autocomplete="current-password" required />
        </FormField>
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2"><input v-model="form.remember" type="checkbox" class="kt-checkbox kt-checkbox-sm" /> {{ t('common.remember_me') }}</label>
            <Link :href="route('password.request')" class="kt-link">{{ t('common.forgot_password') }}</Link>
        </div>
        <KtButton type="submit" :loading="form.processing" class="w-full justify-center">{{ t('common.login') }}</KtButton>
    </form>
</template>
