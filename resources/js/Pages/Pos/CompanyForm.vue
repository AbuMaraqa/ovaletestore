<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { useTrans } from '@/Composables/useTrans';
import type { PosCompany } from '@/types/pos';

/** POS: add a company in the field, or correct its contact details. Big touch targets for the iPad. */
const props = defineProps<{ company: PosCompany | null }>();
const { t } = useTrans();

const form = useForm({
    name: props.company?.name ?? '',
    contact_name: props.company?.contact_name ?? '',
    phone: props.company?.phone ?? '',
    city: props.company?.city ?? '',
    address: props.company?.address ?? '',
});
const submit = () => (props.company ? form.put(route('pos.companies.update', props.company.id)) : form.post(route('pos.companies.store')));

const fields = [
    { key: 'name', label: 'pos.company_name', required: true, type: 'text', autocomplete: 'organization' },
    { key: 'contact_name', label: 'pos.contact_name', required: false, type: 'text', autocomplete: 'name' },
    { key: 'phone', label: 'pos.phone', required: false, type: 'tel', autocomplete: 'tel' },
    { key: 'city', label: 'pos.city', required: false, type: 'text', autocomplete: 'address-level2' },
] as const;
</script>

<template>
    <div class="mx-auto max-w-2xl p-4 sm:p-6">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">{{ company ? t('pos.edit_company') : t('pos.new_company') }}</h1>
            <Link :href="company ? route('pos.create', company.id) : route('pos.index')" class="btn-ghost h-12 px-5">{{ t('common.back') }}</Link>
        </div>
        <p class="mt-1 text-sm text-stone-500">{{ company ? t('pos.edit_company_help') : t('pos.new_company_help') }}</p>

        <form class="mt-6 space-y-5 rounded-3xl bg-white p-6 ring-1 ring-stone-200" novalidate @submit.prevent="submit">
            <div class="grid gap-5 sm:grid-cols-2">
                <label v-for="f in fields" :key="f.key" :class="['block', f.key === 'name' && 'sm:col-span-2']">
                    <span class="mb-1.5 block text-sm font-semibold">
                        {{ t(f.label) }} <span v-if="f.required" class="text-red-600" aria-hidden="true">*</span>
                    </span>
                    <input
                        v-model="form[f.key]"
                        :type="f.type"
                        :autocomplete="f.autocomplete"
                        :dir="f.type === 'tel' ? 'ltr' : undefined"
                        :inputmode="f.type === 'tel' ? 'tel' : undefined"
                        :required="f.required"
                        :aria-invalid="!!form.errors[f.key]"
                        class="field h-14 rounded-2xl text-base"
                    />
                    <span v-if="form.errors[f.key]" class="mt-1 block text-sm text-red-600" role="alert">{{ form.errors[f.key] }}</span>
                </label>
            </div>
            <label class="block">
                <span class="mb-1.5 block text-sm font-semibold">{{ t('pos.address') }}</span>
                <textarea v-model="form.address" rows="2" class="field rounded-2xl text-base" :aria-invalid="!!form.errors.address" />
                <span v-if="form.errors.address" class="mt-1 block text-sm text-red-600" role="alert">{{ form.errors.address }}</span>
            </label>

            <button type="submit" class="btn-primary h-14 w-full text-base" :disabled="form.processing">
                {{ form.processing ? t('common.loading') : company ? t('common.save') : t('pos.create_company') }}
            </button>
        </form>
    </div>
</template>
