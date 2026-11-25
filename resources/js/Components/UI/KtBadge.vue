<script setup lang="ts">
withDefaults(
    defineProps<{ variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'destructive' | 'info' | 'mono'; appearance?: 'solid' | 'outline' | 'light'; size?: 'sm' | 'md' }>(),
    { variant: 'secondary', appearance: 'light', size: 'md' },
);
</script>

<template>
    <!-- md (12px) by default: Arabic glyphs at 11px are hard to read in dense tables. -->
    <span :class="['kt-badge', size === 'sm' && 'kt-badge-sm', `kt-badge-${variant}`, appearance !== 'solid' && `kt-badge-${appearance}`]"><slot /></span>
</template>
