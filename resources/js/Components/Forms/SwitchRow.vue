<script setup lang="ts">
/** A whole-row toggle: label + description on the start side, the switch on the end; the row is the label. */
const model = defineModel<boolean>({ required: true });
defineProps<{ id: string; label: string; description?: string }>();
</script>

<template>
    <label :for="id" class="flex cursor-pointer items-start justify-between gap-4 rounded-lg border border-border p-3.5 transition-colors hover:bg-accent/40">
        <span class="min-w-0">
            <span class="block text-sm font-medium text-foreground">{{ label }}</span>
            <span v-if="description" class="mt-0.5 block text-xs text-secondary-foreground">{{ description }}</span>
        </span>
        <input :id="id" v-model="model" type="checkbox" class="kt-switch mt-0.5 shrink-0" />
    </label>
</template>
