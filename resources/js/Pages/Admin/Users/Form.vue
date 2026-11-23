<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps<{
    user: { id: number; name: string; email: string; phone: string | null; is_active: boolean; roles: string[]; is_self?: boolean } | null;
    roles: string[];
}>();

const { t } = useTrans();
const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    phone: props.user?.phone ?? '',
    password: '',
    password_confirmation: '',
    is_active: props.user?.is_active ?? true,
    roles: props.user?.roles ?? [],
});

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => form.reset('password', 'password_confirmation') };
    props.user ? form.put(route('admin.users.update', props.user.id), options) : form.post(route('admin.users.store'), options);
}
</script>

<template>
    <PageHeader :title="user ? t('admin.edit_user') : t('admin.new_user')">
        <template #actions>
            <KtButton :href="route('admin.users.index')" variant="ghost">{{ t('common.back') }}</KtButton>
            <KtButton type="submit" form="user-form" :loading="form.processing" icon="check">{{ t('common.save') }}</KtButton>
        </template>
    </PageHeader>

    <form id="user-form" class="grid gap-6 xl:grid-cols-12" autocomplete="off" @submit.prevent="submit">
        <KtCard :title="t('admin.general')" class="xl:col-span-8">
            <div class="grid gap-5 md:grid-cols-2">
                <FormField id="name" :label="t('admin.name')" :error="form.errors.name" required><input id="name" v-model="form.name" class="kt-input" /></FormField>
                <FormField id="email" :label="t('common.email')" :error="form.errors.email" required><input id="email" v-model="form.email" type="email" class="kt-input" dir="ltr" /></FormField>
                <FormField id="phone" :label="t('admin.phone')" :error="form.errors.phone"><input id="phone" v-model="form.phone" type="tel" class="kt-input" dir="ltr" /></FormField>
                <div />
                <FormField id="password" :label="t('common.password')" :help="user ? t('admin.password_help') : undefined" :error="form.errors.password" :required="!user">
                    <input id="password" v-model="form.password" type="password" class="kt-input" autocomplete="new-password" />
                </FormField>
                <FormField id="password_confirmation" :label="t('common.confirm_password')">
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="kt-input" autocomplete="new-password" />
                </FormField>
            </div>
        </KtCard>
        <KtCard :title="t('admin.roles_assigned')" class="xl:col-span-4">
            <div class="space-y-3">
                <label class="flex items-center justify-between text-sm">{{ t('admin.active') }} <input v-model="form.is_active" type="checkbox" class="kt-switch" :disabled="user?.is_self" /></label>
                <p v-if="form.errors.is_active" class="text-sm text-destructive">{{ form.errors.is_active }}</p>
                <label v-for="role in roles" :key="role" class="flex items-center gap-2.5 text-sm">
                    <input v-model="form.roles" type="checkbox" :value="role" class="kt-checkbox" :disabled="user?.is_self" /> {{ role }}
                </label>
                <p v-if="user?.is_self" class="text-sm text-muted-foreground">{{ t('admin.own_account_locked') }}</p>
                <p v-if="form.errors.roles" class="text-sm text-destructive">{{ form.errors.roles }}</p>
            </div>
        </KtCard>
    </form>
</template>
