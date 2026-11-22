<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DeleteButton from '@/Components/Admin/DeleteButton.vue';
import KtBadge from '@/Components/UI/KtBadge.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

defineProps<{ attributes: { id: number; name: string; code: string; display_type: string; terms: string[] }[] }>();
const { t } = useTrans();
</script>

<template>
    <PageHeader :title="t('admin.attributes')">
        <template #actions><KtButton :href="route('admin.attributes.create')" icon="plus">{{ t('admin.new_attribute') }}</KtButton></template>
    </PageHeader>
    <KtCard flush>
        <div class="kt-table-wrapper overflow-x-auto">
            <table class="kt-table">
                <thead>
                    <tr><th>{{ t('admin.name') }}</th><th>{{ t('admin.code') }}</th><th>{{ t('admin.terms') }}</th><th class="text-end">{{ t('common.actions') }}</th></tr>
                </thead>
                <tbody>
                    <tr v-for="a in attributes" :key="a.id">
                        <td><Link :href="route('admin.attributes.edit', a.id)" class="font-medium text-mono hover:text-primary">{{ a.name }}</Link></td>
                        <td class="font-mono text-xs" dir="ltr">{{ a.code }}</td>
                        <td><div class="flex flex-wrap gap-1"><KtBadge v-for="term in a.terms" :key="term">{{ term }}</KtBadge></div></td>
                        <td class="text-end"><DeleteButton :href="route('admin.attributes.destroy', a.id)" /></td>
                    </tr>
                    <tr v-if="!attributes.length"><td colspan="4" class="py-10 text-center text-muted-foreground">{{ t('common.no_results') }}</td></tr>
                </tbody>
            </table>
        </div>
    </KtCard>
</template>
