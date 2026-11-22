<script setup lang="ts">
import KtBadge from '@/Components/UI/KtBadge.vue';
import { useTrans } from '@/Composables/useTrans';

defineProps<{ status: string }>();
const { t } = useTrans();

const variants: Record<string, 'warning' | 'info' | 'primary' | 'success' | 'destructive' | 'secondary'> = {
    pending: 'warning',
    confirmed: 'info',
    processing: 'primary',
    shipped: 'info',
    completed: 'success',
    cancelled: 'destructive',
};
</script>

<template>
    <KtBadge :variant="variants[status] ?? 'secondary'">{{ t(`orders.status_${status}`) }}</KtBadge>
</template>
