<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps<{ customer: { id: number; name: string; email: string; phone: string | null; is_active: boolean } }>();
const { t } = useTrans();
const form = useForm({ name: props.customer.name, email: props.customer.email, phone: props.customer.phone ?? '', is_active: props.customer.is_active });
const submit = () => form.put(route('admin.customers.update', props.customer.id));
</script>

<template>
    <PageHeader :title="t('customers.edit')" :description="customer.name">
        <template #actions>
            <KtButton :href="route('admin.customers.show', customer.id)" variant="outline">{{ t('common.back') }}</KtButton>
        </template>
    </PageHeader>

    <KtCard class="max-w-2xl">
        <form class="space-y-5" @submit.prevent="submit">
            <FormField id="name" :label="t('admin.name')" :error="form.errors.name" required>
                <input id="name" v-model="form.name" class="kt-input" maxlength="120" required />
            </FormField>
            <FormField id="email" :label="t('common.email')" :error="form.errors.email" required>
                <input id="email" v-model="form.email" type="email" class="kt-input" dir="ltr" required />
            </FormField>
            <FormField id="phone" :label="t('admin.phone')" :error="form.errors.phone">
                <input id="phone" v-model="form.phone" type="tel" class="kt-input" dir="ltr" />
            </FormField>
            <label class="flex items-center gap-3 text-sm">
                <input v-model="form.is_active" type="checkbox" class="kt-switch" />
                {{ t('admin.active') }}
            </label>
            <div class="flex justify-end">
                <KtButton type="submit" :loading="form.processing" icon="check">{{ t('common.save') }}</KtButton>
            </div>
        </form>
    </KtCard>
</template>
