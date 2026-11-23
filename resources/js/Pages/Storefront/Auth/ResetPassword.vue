<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AuthCard from '@/Components/Storefront/AuthCard.vue';
import StoreField from '@/Components/Storefront/StoreField.vue';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps<{ email: string; token: string }>();
const { t } = useTrans();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const submit = () => form.post(route('password.update'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <AuthCard :title="t('common.reset_password')">
        <form class="space-y-5" @submit.prevent="submit">
            <StoreField id="email" v-model="form.email" type="email" :label="t('common.email')" :error="form.errors.email" autocomplete="username" dir="ltr" required />
            <StoreField id="password" v-model="form.password" type="password" :label="t('common.password')" :error="form.errors.password" autocomplete="new-password" required />
            <StoreField id="password_confirmation" v-model="form.password_confirmation" type="password" :label="t('common.confirm_password')" autocomplete="new-password" required />
            <button type="submit" class="btn-primary h-12 w-full" :disabled="form.processing">{{ t('common.reset_password') }}</button>
        </form>
    </AuthCard>
</template>
