<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import { useTrans } from '@/Composables/useTrans';

const { t } = useTrans();
const useRecovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });
const submit = () => form.post(route('two-factor.login.store'));
</script>

<template>
    <Head :title="t('common.two_factor_code')" />
    <h1 class="text-2xl font-semibold text-mono">{{ useRecovery ? t('common.recovery_code') : t('common.two_factor_code') }}</h1>
    <form class="mt-8 space-y-5" @submit.prevent="submit">
        <FormField v-if="!useRecovery" id="code" :label="t('common.two_factor_code')" :error="form.errors.code">
            <input id="code" v-model="form.code" inputmode="numeric" autocomplete="one-time-code" class="kt-input text-center font-mono tracking-[0.5em]" maxlength="6" autofocus dir="ltr" />
        </FormField>
        <FormField v-else id="recovery_code" :label="t('common.recovery_code')" :error="form.errors.recovery_code">
            <input id="recovery_code" v-model="form.recovery_code" class="kt-input font-mono" autocomplete="one-time-code" dir="ltr" />
        </FormField>
        <KtButton type="submit" :loading="form.processing" class="w-full justify-center">{{ t('common.verify') }}</KtButton>
        <button type="button" class="kt-link block w-full text-center text-sm" @click="useRecovery = !useRecovery">
            {{ useRecovery ? t('common.two_factor_code') : t('common.recovery_code') }}
        </button>
    </form>
</template>
