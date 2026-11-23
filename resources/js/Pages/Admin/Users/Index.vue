<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DeleteButton from '@/Components/Admin/DeleteButton.vue';
import KtBadge from '@/Components/UI/KtBadge.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtPagination from '@/Components/UI/KtPagination.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import type { Avatar, PaginationLink } from '@/types';

defineProps<{
    users: {
        data: { id: number; name: string; email: string; avatar: Avatar; is_active: boolean; roles: string[]; two_factor: boolean }[];
        links: PaginationLink[]; total: number; from: number | null; to: number | null;
    };
}>();
const { t } = useTrans();
</script>

<template>
    <PageHeader :title="t('admin.users')">
        <template #actions><KtButton :href="route('admin.users.create')" icon="plus">{{ t('admin.new_user') }}</KtButton></template>
    </PageHeader>
    <KtCard flush>
        <div class="kt-table-wrapper overflow-x-auto">
            <table class="kt-table">
                <thead><tr><th>{{ t('admin.name') }}</th><th>{{ t('admin.roles_assigned') }}</th><th>{{ t('common.status') }}</th><th class="text-end">{{ t('common.actions') }}</th></tr></thead>
                <tbody>
                    <tr v-for="u in users.data" :key="u.id">
                        <td>
                            <div class="flex items-center gap-3">
                                <UserAvatar :avatar="u.avatar" :name="u.name" :ring="false" />
                                <div>
                                    <Link :href="route('admin.users.edit', u.id)" class="font-medium text-mono hover:text-primary">{{ u.name }}</Link>
                                    <p class="text-xs text-muted-foreground" dir="ltr">{{ u.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td><div class="flex flex-wrap gap-1"><KtBadge v-for="r in u.roles" :key="r" variant="primary">{{ r }}</KtBadge></div></td>
                        <td class="space-x-1 rtl:space-x-reverse">
                            <KtBadge :variant="u.is_active ? 'success' : 'secondary'">{{ u.is_active ? t('admin.active') : t('admin.inactive') }}</KtBadge>
                            <KtBadge v-if="u.two_factor" variant="info">2FA</KtBadge>
                        </td>
                        <td class="text-end"><DeleteButton v-if="!u.roles.includes('super-admin')" :href="route('admin.users.destroy', u.id)" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <template #footer><KtPagination :links="users.links" :total="users.total" :from="users.from" :to="users.to" class="w-full" /></template>
    </KtCard>
</template>
