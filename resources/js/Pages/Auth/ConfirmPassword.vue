<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import { useTrans } from '@/Composables/useTrans';

const { t } = useTrans();
const form = useForm({ password: '' });
const submit = () => form.post(route('password.confirm.store'), { onFinish: () => form.reset() });
</script>

<template>
    <Head :title="t('common.confirm_password')" />
    <h1 class="text-2xl font-semibold text-mono">{{ t('common.confirm_password') }}</h1>
    <form class="mt-8 space-y-5" @submit.prevent="submit">
        <FormField id="password" :label="t('common.password')" :error="form.errors.password">
            <input id="password" v-model="form.password" type="password" class="kt-input" autocomplete="current-password" required autofocus />
        </FormField>
        <KtButton type="submit" :loading="form.processing" class="w-full justify-center">{{ t('common.verify') }}</KtButton>
    </form>
</template>
