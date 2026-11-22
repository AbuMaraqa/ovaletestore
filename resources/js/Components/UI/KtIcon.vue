<script setup lang="ts">
/** Keenicons (Metronic) — e.g. <KtIcon name="basket" />, style: filled | outline | duotone | solid. */
withDefaults(defineProps<{ name: string; variant?: 'filled' | 'outline' | 'duotone' | 'solid' }>(), { variant: 'filled' });
</script>

<template>
    <i :class="[`ki-${variant}`, `ki-${name}`]" aria-hidden="true" />
</template>
