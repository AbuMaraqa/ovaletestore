<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import DeleteButton from '@/Components/Admin/DeleteButton.vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
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

type VisitHooks = { preserveScroll: boolean; onError: () => void; onSuccess: () => void };
function submit(hooks: VisitHooks): void {
    if (props.company) form.put(route('admin.companies.update', props.company.id), hooks);
    else form.post(route('admin.companies.store'), hooks);
}
</script>

<template>

    <FormPage id="company-form" :form="form" :action="submit" :title="company ? company.name : t('admin.new_company')" :back-href="company ? route('admin.companies.show', company.id) : route('admin.companies.index')">
        <template #actions><DeleteButton v-if="company" :href="route('admin.companies.destroy', company.id)" /></template>
        <div class="grid gap-6 xl:grid-cols-12">
        <KtCard :title="t('admin.general')" class="xl:col-span-8">
            <div class="grid gap-5 md:grid-cols-2">
                <FormField id="name" :label="t('admin.name')" error-key="name" required class="md:col-span-2">
                    <input id="name" v-model="form.name" class="kt-input" />
                </FormField>
                <FormField id="contact_name" :label="t('admin.contact_name')" error-key="contact_name">
                    <input id="contact_name" v-model="form.contact_name" class="kt-input" />
                </FormField>
                <FormField id="phone" :label="t('admin.phone')" error-key="phone">
                    <input id="phone" v-model="form.phone" type="tel" class="kt-input" dir="ltr" />
                </FormField>
                <FormField id="city" :label="t('admin.city')" error-key="city">
                    <input id="city" v-model="form.city" class="kt-input" />
                </FormField>
                <FormField id="address" :label="t('admin.address')" error-key="address">
                    <input id="address" v-model="form.address" class="kt-input" />
                </FormField>
                <FormField id="notes" :label="t('admin.notes')" error-key="notes" class="md:col-span-2">
                    <textarea id="notes" v-model="form.notes" rows="3" class="kt-textarea" />
                </FormField>
            </div>
        </KtCard>
        <KtCard :title="t('common.status')" class="xl:col-span-4">
            <label class="flex items-center justify-between text-sm">{{ t('admin.active') }} <input v-model="form.is_active" type="checkbox" class="kt-switch" /></label>
        </KtCard>
        </div>
    </FormPage>
</template>
