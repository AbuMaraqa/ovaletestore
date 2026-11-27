<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Storefront/Icon.vue';

/** Section title (+ optional subtitle) with a plain "view all" text link at the end. */
withDefaults(defineProps<{ title: string; subtitle?: string; link?: string; linkLabel?: string; center?: boolean }>(), {
    subtitle: undefined,
    link: undefined,
    linkLabel: undefined,
    center: false,
});
</script>

<template>
    <div :class="['flex gap-4', center ? 'flex-col items-center text-center' : 'items-end justify-between']">
        <div>
            <h2 class="text-[1.75rem] leading-tight font-extrabold text-cocoa sm:text-[2.125rem]">{{ title }}</h2>
            <p v-if="subtitle" class="mt-2 max-w-xl text-stone-500">{{ subtitle }}</p>
        </div>
        <Link v-if="link" :href="link" class="group inline-flex shrink-0 items-center gap-1.5 pb-1 text-sm font-semibold text-cocoa hover:text-brand-700">
            <span class="underline decoration-hairline decoration-2 underline-offset-[6px] transition group-hover:decoration-brand-400">{{ linkLabel }}</span>
            <Icon name="arrow" class="size-4 transition group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5" />
        </Link>
    </div>
</template>
