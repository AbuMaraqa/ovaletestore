<script setup lang="ts">
/** KTUI form item: label + control (slot) + help + error, wired for a11y via `id`. */
defineProps<{ id: string; label?: string; error?: string; help?: string; required?: boolean }>();
</script>

<template>
    <div class="kt-form-item">
        <label v-if="label" :for="id" class="kt-form-label">
            {{ label }}<span v-if="required" class="text-destructive" aria-hidden="true">*</span>
        </label>
        <div class="kt-form-control">
            <slot :invalid="!!error" :described-by="error ? `${id}-error` : help ? `${id}-help` : undefined" />
        </div>
        <p v-if="help && !error" :id="`${id}-help`" class="kt-form-description">{{ help }}</p>
        <p v-if="error" :id="`${id}-error`" class="kt-form-message text-destructive">{{ error }}</p>
    </div>
</template>
