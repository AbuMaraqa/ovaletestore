<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

interface Group {
    key: string;
    label: string;
    permissions: { name: string; label: string; grantable: boolean }[];
}

const props = defineProps<{
    role: { id: number; name: string; label: string; built_in: boolean; locked: boolean; permissions: string[]; users_count: number } | null;
    groups: Group[];
}>();

const { t } = useTrans();
const { can } = useCan();
const form = useForm({ name: props.role?.name ?? '', permissions: [...(props.role?.permissions ?? [])] });

const readOnly = computed(() => props.role?.locked || !can(props.role ? 'roles.update' : 'roles.create'));
const total = computed(() => props.groups.reduce((n, g) => n + g.permissions.length, 0));
const has = (name: string) => form.permissions.includes(name);
const toggle = (name: string) => (form.permissions = has(name) ? form.permissions.filter((p) => p !== name) : [...form.permissions, name]);
const grantable = (g: Group) => g.permissions.filter((p) => p.grantable).map((p) => p.name);
/** Only what this user may change counts, so the group box can never get stuck on "some". */
const groupState = (g: Group) => {
    const names = grantable(g);
    const count = names.filter(has).length;
    return count === 0 ? 'none' : count === names.length ? 'all' : 'some';
};
function toggleGroup(g: Group): void {
    const names = grantable(g);
    form.permissions = groupState(g) === 'all' ? form.permissions.filter((p) => !names.includes(p)) : [...new Set([...form.permissions, ...names])];
}
const setAll = (on: boolean) => (form.permissions = on ? props.groups.flatMap(grantable) : form.permissions.filter((p) => !props.groups.flatMap(grantable).includes(p)));

const submit = () => (props.role ? form.put(route('admin.roles.update', props.role.id), { preserveScroll: true }) : form.post(route('admin.roles.store')));
</script>

<template>
    <PageHeader :title="role ? role.label : t('permissions.new_role')" :description="t('permissions.subtitle')">
        <template #actions>
            <KtButton :href="route('admin.roles.index')" variant="outline">{{ t('common.back') }}</KtButton>
            <KtButton v-if="!readOnly" type="submit" form="role-form" :loading="form.processing" icon="check">{{ t('common.save') }}</KtButton>
        </template>
    </PageHeader>

    <form id="role-form" class="grid gap-6 xl:grid-cols-12" @submit.prevent="submit">
        <aside class="space-y-6 xl:col-span-4">
            <KtCard :title="t('permissions.role')">
                <div class="space-y-4">
                    <FormField id="name" :label="t('permissions.name')" :error="form.errors.name">
                        <input id="name" v-model="form.name" class="kt-input" maxlength="60" required :disabled="readOnly || role?.built_in" />
                    </FormField>
                    <p v-if="role?.locked" class="flex gap-2 rounded-lg bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-300"><KtIcon name="crown" class="mt-0.5" />{{ t('permissions.locked') }}</p>
                    <p v-else-if="role?.built_in" class="flex gap-2 rounded-lg bg-muted p-3 text-sm text-secondary-foreground"><KtIcon name="information-2" class="mt-0.5" />{{ t('permissions.built_in_note') }}</p>
                    <div class="flex items-center justify-between rounded-lg border border-border p-3 text-sm">
                        <span class="text-secondary-foreground">{{ t('permissions.permissions_count') }}</span>
                        <span class="font-semibold tabular-nums text-mono">{{ t('permissions.selected_of', { count: form.permissions.length, total }) }}</span>
                    </div>
                    <div v-if="role" class="flex items-center justify-between rounded-lg border border-border p-3 text-sm">
                        <span class="text-secondary-foreground">{{ t('permissions.users_count') }}</span>
                        <span class="font-semibold tabular-nums text-mono">{{ role.users_count }}</span>
                    </div>
                    <div v-if="!readOnly" class="flex gap-2">
                        <button type="button" class="kt-btn kt-btn-sm kt-btn-outline grow justify-center" @click="setAll(true)">{{ t('permissions.select_all') }}</button>
                        <button type="button" class="kt-btn kt-btn-sm kt-btn-outline grow justify-center" @click="setAll(false)">{{ t('permissions.clear_all') }}</button>
                    </div>
                    <p v-if="form.errors.permissions" class="text-sm text-destructive">{{ form.errors.permissions }}</p>
                </div>
            </KtCard>
        </aside>

        <div class="grid gap-5 md:grid-cols-2 xl:col-span-8">
            <section v-for="group in groups" :key="group.key" class="kt-card">
                <header class="kt-card-header min-h-14">
                    <label class="flex cursor-pointer items-center gap-2.5">
                        <input
                            type="checkbox"
                            class="kt-checkbox kt-checkbox-sm"
                            :checked="groupState(group) === 'all'"
                            :indeterminate="groupState(group) === 'some'"
                            :disabled="readOnly || !grantable(group).length"
                            @change="toggleGroup(group)"
                        />
                        <span class="kt-card-title text-sm">{{ group.label }}</span>
                    </label>
                    <span class="text-xs tabular-nums text-muted-foreground">{{ group.permissions.filter((p) => has(p.name)).length }}/{{ group.permissions.length }}</span>
                </header>
                <div class="kt-card-content flex flex-col gap-1 py-3">
                    <label
                        v-for="permission in group.permissions"
                        :key="permission.name"
                        :class="['flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm', readOnly || !permission.grantable ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:bg-accent/60']"
                        :title="permission.grantable ? permission.name : t('permissions.not_grantable')"
                    >
                        <input type="checkbox" class="kt-switch kt-switch-sm" :checked="has(permission.name)" :disabled="readOnly || !permission.grantable" @change="toggle(permission.name)" />
                        {{ permission.label }}
                        <span v-if="!permission.grantable" class="ms-auto flex items-center gap-1 text-xs text-muted-foreground">
                            <KtIcon name="lock" /><span class="sr-only">{{ t('permissions.not_grantable') }}</span>
                        </span>
                    </label>
                </div>
            </section>
        </div>
    </form>
</template>
