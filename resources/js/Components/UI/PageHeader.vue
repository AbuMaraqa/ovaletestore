<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

/** Metronic toolbar: title + description on the start side, actions on the end side. */
const props = defineProps<{ title: string; description?: string }>();
</script>

<template>
    <Head :title="props.title" />
    <div class="flex flex-wrap items-center justify-between gap-5 pb-7.5 lg:items-end">
        <div class="flex flex-col justify-center gap-2">
            <h1 class="text-xl font-medium leading-none text-mono">{{ title }}</h1>
            <div v-if="description" class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">{{ description }}</div>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2.5"><slot name="actions" /></div>
    </div>
</template>
