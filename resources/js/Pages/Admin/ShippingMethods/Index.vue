<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DeleteButton from '@/Components/Admin/DeleteButton.vue';
import KtBadge from '@/Components/UI/KtBadge.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

defineProps<{
    methods: { id: number; name: string; type: 'delivery' | 'pickup'; cost: string | null; free_over: string | null; min_order: string | null; is_active: boolean; orders_count: number }[];
}>();
const { t } = useTrans();
</script>

<template>
    <PageHeader :title="t('admin.shipping_methods')" :description="t('admin.shipping_methods_help')">
        <template #actions><KtButton :href="route('admin.shipping-methods.create')" icon="plus">{{ t('admin.new_shipping_method') }}</KtButton></template>
    </PageHeader>
    <KtCard flush>
        <div class="kt-table-wrapper overflow-x-auto">
            <table class="kt-table">
                <thead>
                    <tr>
                        <th>{{ t('admin.name') }}</th>
                        <th>{{ t('admin.shipping_type') }}</th>
                        <th>{{ t('admin.shipping_cost') }}</th>
                        <th>{{ t('admin.free_over') }}</th>
                        <th>{{ t('admin.min_order_for_method') }}</th>
                        <th class="text-center">{{ t('admin.orders') }}</th>
                        <th>{{ t('common.status') }}</th>
                        <th class="text-end">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in methods" :key="m.id">
                        <td><Link :href="route('admin.shipping-methods.edit', m.id)" class="font-medium text-mono hover:text-primary">{{ m.name }}</Link></td>
                        <td>{{ t(`admin.shipping_type_${m.type}`) }}</td>
                        <td dir="ltr" class="text-start tabular-nums">{{ m.cost ?? t('cart.free') }}</td>
                        <td dir="ltr" class="text-start tabular-nums">{{ m.free_over ?? '—' }}</td>
                        <td dir="ltr" class="text-start tabular-nums">{{ m.min_order ?? '—' }}</td>
                        <td class="text-center tabular-nums">{{ m.orders_count }}</td>
                        <td><KtBadge :variant="m.is_active ? 'success' : 'secondary'">{{ m.is_active ? t('admin.active') : t('admin.inactive') }}</KtBadge></td>
                        <td class="text-end"><DeleteButton :href="route('admin.shipping-methods.destroy', m.id)" /></td>
                    </tr>
                    <tr v-if="!methods.length"><td colspan="8" class="py-10 text-center text-muted-foreground">{{ t('admin.no_shipping_methods') }}</td></tr>
                </tbody>
            </table>
        </div>
    </KtCard>
</template>
