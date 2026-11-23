<script setup lang="ts">
import { useTrans } from '@/Composables/useTrans';

defineProps<{ status: string }>();
const { t } = useTrans();

const tones: Record<string, string> = {
    pending: 'bg-amber-50 text-amber-700 ring-amber-200',
    confirmed: 'bg-sky-50 text-sky-700 ring-sky-200',
    processing: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    shipped: 'bg-violet-50 text-violet-700 ring-violet-200',
    completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    cancelled: 'bg-red-50 text-red-700 ring-red-200',
};
</script>

<template>
    <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1', tones[status] ?? 'bg-stone-50 text-stone-600 ring-stone-200']">
        {{ t(`orders.status_${status}`) }}
    </span>
</template>
