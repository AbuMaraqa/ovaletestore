<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Storefront/Icon.vue';

defineProps<{ title: string; link?: string; linkLabel?: string }>();
</script>

<template>
    <div class="flex items-end justify-between gap-4">
        <h2 class="text-2xl font-bold tracking-tight text-cocoa sm:text-3xl">{{ title }}</h2>
        <Link v-if="link" :href="link" class="group inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-brand-700 hover:text-brand-800">
            {{ linkLabel }}
            <Icon name="arrow" class="size-4 transition group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5" />
        </Link>
    </div>
</template>
