<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import FormField from '@/Components/Forms/FormField.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import { useTrans } from '@/Composables/useTrans';

/** Create a customer account (staff) or edit one. A new account may get a password, or an email link to set it. */
const props = defineProps<{ customer: { id: number; name: string; email: string; phone: string | null; is_active: boolean } | null }>();
const { t } = useTrans();
const localeNames: Record<string, string> = { ar: 'العربية', en: 'English', he: 'עברית' };

const form = useForm({
    name: props.customer?.name ?? '',
    email: props.customer?.email ?? '',
    phone: props.customer?.phone ?? '',
    is_active: props.customer?.is_active ?? true,
    locale: 'ar',
    password: '',
    send_password_link: true,
});
type VisitHooks = { preserveScroll: boolean; onError: () => void; onSuccess: () => void };
const submit = (hooks: VisitHooks) => (props.customer ? form.put(route('admin.customers.update', props.customer.id), hooks) : form.post(route('admin.customers.store'), hooks));
</script>

<template>
    <FormPage id="customer-form" :form="form" :action="submit" :title="customer ? t('customers.edit') : t('customers.new')" :back-href="customer ? route('admin.customers.show', customer.id) : route('admin.customers.index')">
        <template #description>{{ customer?.name ?? t('customers.new_help') }}</template>

    <KtCard class="max-w-2xl">
        <div class="space-y-5">
            <FormField id="name" :label="t('admin.name')" error-key="name" required>
                <input id="name" v-model="form.name" class="kt-input" maxlength="120" autocomplete="off" required />
            </FormField>
            <div class="grid gap-5 sm:grid-cols-2">
                <FormField id="email" :label="t('common.email')" error-key="email" required>
                    <input id="email" v-model="form.email" type="email" class="kt-input" dir="ltr" autocomplete="off" required />
                </FormField>
                <FormField id="phone" :label="t('admin.phone')" error-key="phone">
                    <input id="phone" v-model="form.phone" type="tel" class="kt-input" dir="ltr" autocomplete="off" />
                </FormField>
            </div>

            <template v-if="!customer">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField id="locale" :label="t('customers.language')" :help="t('customers.language_help')" error-key="locale">
                        <select id="locale" v-model="form.locale" class="kt-select">
                            <option v-for="(label, code) in localeNames" :key="code" :value="code">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField id="password" :label="t('customers.password_optional')" :help="t('customers.password_help')" error-key="password">
                        <input id="password" v-model="form.password" type="password" class="kt-input" dir="ltr" autocomplete="new-password" />
                    </FormField>
                </div>
                <label v-if="!form.password" class="flex items-center gap-3 text-sm">
                    <input v-model="form.send_password_link" type="checkbox" class="kt-checkbox" />
                    {{ t('customers.send_password_link') }}
                </label>
            </template>

            <label class="flex items-center gap-3 text-sm">
                <input v-model="form.is_active" type="checkbox" class="kt-switch" />
                {{ t('admin.active') }}
            </label>
        </div>
    </KtCard>
    </FormPage>
</template>
