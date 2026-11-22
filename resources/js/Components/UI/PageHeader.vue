<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ title: string; description?: string }>();
</script>

<template>
    <Head :title="props.title" />
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-mono">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
    </div>
</template>
