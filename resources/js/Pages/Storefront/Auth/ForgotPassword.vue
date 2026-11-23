<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AuthCard from '@/Components/Storefront/AuthCard.vue';
import StoreField from '@/Components/Storefront/StoreField.vue';
import { useTrans } from '@/Composables/useTrans';

defineProps<{ status?: string | null }>();
const { t } = useTrans();
const form = useForm({ email: '' });
const submit = () => form.post(route('password.email'));
</script>

<template>
    <AuthCard :title="t('common.forgot_password')">
        <p v-if="status" class="mb-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ status }}</p>
        <form class="space-y-5" @submit.prevent="submit">
            <StoreField id="email" v-model="form.email" type="email" :label="t('common.email')" :error="form.errors.email" autocomplete="username" dir="ltr" required />
            <button type="submit" class="btn-primary h-12 w-full" :disabled="form.processing">{{ t('common.send_reset_link') }}</button>
        </form>
        <Link :href="route('login')" class="mt-6 block text-center text-sm font-semibold text-brand-700 hover:underline">{{ t('common.back') }}</Link>
    </AuthCard>
</template>
