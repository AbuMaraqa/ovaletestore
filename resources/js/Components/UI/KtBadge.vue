<script setup lang="ts">
withDefaults(
    defineProps<{ variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'destructive' | 'info' | 'mono'; appearance?: 'solid' | 'outline' | 'light' }>(),
    { variant: 'secondary', appearance: 'light' },
);
</script>

<template>
    <span :class="['kt-badge kt-badge-sm', `kt-badge-${variant}`, appearance !== 'solid' && `kt-badge-${appearance}`]"><slot /></span>
</template>
