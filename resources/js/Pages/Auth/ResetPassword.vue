<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps<{ email: string; token: string }>();
const { t } = useTrans();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const submit = () => form.post(route('password.update'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head :title="t('common.reset_password')" />
    <h1 class="text-2xl font-semibold text-mono">{{ t('common.reset_password') }}</h1>
    <form class="mt-8 space-y-5" @submit.prevent="submit">
        <FormField id="email" :label="t('common.email')" :error="form.errors.email">
            <input id="email" v-model="form.email" type="email" class="kt-input" autocomplete="username" required dir="ltr" />
        </FormField>
        <FormField id="password" :label="t('common.password')" :error="form.errors.password">
            <input id="password" v-model="form.password" type="password" class="kt-input" autocomplete="new-password" required />
        </FormField>
        <FormField id="password_confirmation" :label="t('common.confirm_password')">
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="kt-input" autocomplete="new-password" required />
        </FormField>
        <KtButton type="submit" :loading="form.processing" class="w-full justify-center">{{ t('common.reset_password') }}</KtButton>
    </form>
</template>
