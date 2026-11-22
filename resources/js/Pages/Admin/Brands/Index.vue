<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DeleteButton from '@/Components/Admin/DeleteButton.vue';
import KtBadge from '@/Components/UI/KtBadge.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

defineProps<{ brands: { id: number; name: string; is_active: boolean; is_featured: boolean; products_count: number; logo: string | null }[] }>();
const { t } = useTrans();
</script>

<template>
    <PageHeader :title="t('admin.brands')">
        <template #actions><KtButton :href="route('admin.brands.create')" icon="plus">{{ t('admin.new_brand') }}</KtButton></template>
    </PageHeader>
    <KtCard flush>
        <div class="kt-table-wrapper overflow-x-auto">
            <table class="kt-table">
                <thead>
                    <tr><th>{{ t('admin.name') }}</th><th class="text-center">{{ t('admin.products') }}</th><th>{{ t('common.status') }}</th><th class="text-end">{{ t('common.actions') }}</th></tr>
                </thead>
                <tbody>
                    <tr v-for="b in brands" :key="b.id">
                        <td>
                            <div class="flex items-center gap-3">
                                <img v-if="b.logo" :src="b.logo" alt="" class="size-9 rounded-md bg-white object-contain" loading="lazy" />
                                <Link :href="route('admin.brands.edit', b.id)" class="font-medium text-mono hover:text-primary">{{ b.name }}</Link>
                            </div>
                        </td>
                        <td class="text-center tabular-nums">{{ b.products_count }}</td>
                        <td class="space-x-1 rtl:space-x-reverse">
                            <KtBadge :variant="b.is_active ? 'success' : 'secondary'">{{ b.is_active ? t('admin.active') : t('admin.inactive') }}</KtBadge>
                            <KtBadge v-if="b.is_featured" variant="primary">{{ t('admin.featured') }}</KtBadge>
                        </td>
                        <td class="text-end"><DeleteButton :href="route('admin.brands.destroy', b.id)" /></td>
                    </tr>
                    <tr v-if="!brands.length"><td colspan="4" class="py-10 text-center text-muted-foreground">{{ t('common.no_results') }}</td></tr>
                </tbody>
            </table>
        </div>
    </KtCard>
</template>
