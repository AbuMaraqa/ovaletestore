<script setup lang="ts">
/** A titled group of fields inside a FormCard, separated from the previous group by a divider. */
defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <div class="form-section">
        <div v-if="title || $slots.aside" class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h3 v-if="title" class="text-sm font-semibold text-mono">{{ title }}</h3>
                <p v-if="description" class="mt-0.5 text-xs text-secondary-foreground">{{ description }}</p>
            </div>
            <slot name="aside" />
        </div>
        <slot />
    </div>
</template>
