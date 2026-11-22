<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import { useTrans } from '@/Composables/useTrans';

defineProps<{ status?: string | null }>();
const { t } = useTrans();
const form = useForm({ email: '' });
const submit = () => form.post(route('password.email'));
</script>

<template>
    <Head :title="t('common.forgot_password')" />
    <h1 class="text-2xl font-semibold text-mono">{{ t('common.forgot_password') }}</h1>
    <p v-if="status" class="kt-alert kt-alert-success mt-6 text-sm">{{ status }}</p>
    <form class="mt-8 space-y-5" @submit.prevent="submit">
        <FormField id="email" :label="t('common.email')" :error="form.errors.email">
            <input id="email" v-model="form.email" type="email" class="kt-input" autocomplete="username" required dir="ltr" />
        </FormField>
        <KtButton type="submit" :loading="form.processing" class="w-full justify-center">{{ t('common.send_reset_link') }}</KtButton>
        <Link :href="route('login')" class="kt-link block text-center text-sm">{{ t('common.back') }}</Link>
    </form>
</template>
