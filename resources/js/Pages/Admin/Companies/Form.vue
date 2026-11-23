<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import DeleteButton from '@/Components/Admin/DeleteButton.vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

type Company = { id: number; name: string; contact_name: string | null; phone: string | null; city: string | null; address: string | null; notes: string | null; is_active: boolean };
const props = defineProps<{ company: Company | null }>();
const { t } = useTrans();

const form = useForm({
    name: props.company?.name ?? '',
    contact_name: props.company?.contact_name ?? '',
    phone: props.company?.phone ?? '',
    city: props.company?.city ?? '',
    address: props.company?.address ?? '',
    notes: props.company?.notes ?? '',
    is_active: props.company?.is_active ?? true,
});

function submit(): void {
    if (props.company) form.put(route('admin.companies.update', props.company.id), { preserveScroll: true });
    else form.post(route('admin.companies.store'));
}
</script>

<template>
    <PageHeader :title="company ? company.name : t('admin.new_company')">
        <template #actions>
            <KtButton :href="company ? route('admin.companies.show', company.id) : route('admin.companies.index')" variant="ghost">{{ t('common.back') }}</KtButton>
            <DeleteButton v-if="company" :href="route('admin.companies.destroy', company.id)" />
            <KtButton type="submit" form="company-form" :loading="form.processing" icon="check">{{ t('common.save') }}</KtButton>
        </template>
    </PageHeader>

    <form id="company-form" class="grid gap-6 xl:grid-cols-12" @submit.prevent="submit">
        <KtCard :title="t('admin.general')" class="xl:col-span-8">
            <div class="grid gap-5 md:grid-cols-2">
                <FormField id="name" :label="t('admin.name')" :error="form.errors.name" required class="md:col-span-2">
                    <input id="name" v-model="form.name" class="kt-input" />
                </FormField>
                <FormField id="contact_name" :label="t('admin.contact_name')" :error="form.errors.contact_name">
                    <input id="contact_name" v-model="form.contact_name" class="kt-input" />
                </FormField>
                <FormField id="phone" :label="t('admin.phone')" :error="form.errors.phone">
                    <input id="phone" v-model="form.phone" type="tel" class="kt-input" dir="ltr" />
                </FormField>
                <FormField id="city" :label="t('admin.city')" :error="form.errors.city">
                    <input id="city" v-model="form.city" class="kt-input" />
                </FormField>
                <FormField id="address" :label="t('admin.address')" :error="form.errors.address">
                    <input id="address" v-model="form.address" class="kt-input" />
                </FormField>
                <FormField id="notes" :label="t('admin.notes')" :error="form.errors.notes" class="md:col-span-2">
                    <textarea id="notes" v-model="form.notes" rows="3" class="kt-textarea" />
                </FormField>
            </div>
        </KtCard>
        <KtCard :title="t('common.status')" class="xl:col-span-4">
            <label class="flex items-center justify-between text-sm">{{ t('admin.active') }} <input v-model="form.is_active" type="checkbox" class="kt-switch" /></label>
        </KtCard>
    </form>
</template>
