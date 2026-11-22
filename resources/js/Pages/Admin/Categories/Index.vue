<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DeleteButton from '@/Components/Admin/DeleteButton.vue';
import KtBadge from '@/Components/UI/KtBadge.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

interface Row { id: number; name: string; parent_id: number | null; is_active: boolean; sort_order: number; products_count: number; image: string | null }
const props = defineProps<{ categories: Row[] }>();
const { t } = useTrans();

/** Flatten the tree (parents first, children indented) for display. */
const rows = computed(() => {
    const out: (Row & { depth: number })[] = [];
    const walk = (parentId: number | null, depth: number) =>
        props.categories.filter((c) => c.parent_id === parentId).forEach((c) => {
            out.push({ ...c, depth });
            walk(c.id, depth + 1);
        });
    walk(null, 0);
    return out;
});
</script>

<template>
    <PageHeader :title="t('admin.categories')">
        <template #actions><KtButton :href="route('admin.categories.create')" icon="plus">{{ t('admin.new_category') }}</KtButton></template>
    </PageHeader>
    <KtCard flush>
        <div class="kt-table-wrapper overflow-x-auto">
            <table class="kt-table">
                <thead>
                    <tr>
                        <th>{{ t('admin.name') }}</th>
                        <th class="text-center">{{ t('admin.products') }}</th>
                        <th class="text-center">{{ t('admin.sort_order') }}</th>
                        <th>{{ t('common.status') }}</th>
                        <th class="text-end">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in rows" :key="c.id">
                        <td>
                            <div class="flex items-center gap-3" :style="{ paddingInlineStart: `${c.depth * 1.5}rem` }">
                                <img v-if="c.image" :src="c.image" alt="" class="size-9 rounded-md object-cover" loading="lazy" />
                                <Link :href="route('admin.categories.edit', c.id)" class="font-medium text-mono hover:text-primary">{{ c.name }}</Link>
                            </div>
                        </td>
                        <td class="text-center tabular-nums">{{ c.products_count }}</td>
                        <td class="text-center tabular-nums">{{ c.sort_order }}</td>
                        <td><KtBadge :variant="c.is_active ? 'success' : 'secondary'">{{ c.is_active ? t('admin.active') : t('admin.inactive') }}</KtBadge></td>
                        <td class="text-end"><DeleteButton :href="route('admin.categories.destroy', c.id)" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </KtCard>
</template>
