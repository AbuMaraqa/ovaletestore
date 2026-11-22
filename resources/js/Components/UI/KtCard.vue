<script setup lang="ts">
withDefaults(defineProps<{ title?: string; description?: string; flush?: boolean }>(), { title: undefined, description: undefined, flush: false });
</script>

<template>
    <section class="kt-card">
        <header v-if="title || $slots.toolbar" class="kt-card-header">
            <div class="kt-card-heading">
                <h3 v-if="title" class="kt-card-title">{{ title }}</h3>
                <p v-if="description" class="kt-card-description">{{ description }}</p>
            </div>
            <div v-if="$slots.toolbar" class="kt-card-toolbar"><slot name="toolbar" /></div>
        </header>
        <div :class="flush ? 'kt-card-table' : 'kt-card-content'"><slot /></div>
        <footer v-if="$slots.footer" class="kt-card-footer"><slot name="footer" /></footer>
    </section>
</template>
