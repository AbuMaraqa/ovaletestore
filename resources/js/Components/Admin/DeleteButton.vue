<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps<{ href: string; label?: string }>();
const { t } = useTrans();

function destroy(): void {
    if (confirm(t('common.confirm_delete'))) router.delete(props.href, { preserveScroll: true });
}
</script>

<template>
    <button type="button" class="kt-btn kt-btn-sm kt-btn-ghost text-destructive" :aria-label="label ?? t('common.delete')" @click="destroy">
        <KtIcon name="trash" />
        <span v-if="label">{{ label }}</span>
    </button>
</template>
