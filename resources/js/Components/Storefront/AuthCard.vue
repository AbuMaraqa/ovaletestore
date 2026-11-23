<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

/** Split auth layout inside the storefront: form card + brand panel. */
defineProps<{ title: string; subtitle?: string }>();
const page = usePage<SharedProps>();
</script>

<template>
    <div class="container-store py-10 lg:py-16">
        <div class="mx-auto grid max-w-5xl overflow-hidden rounded-[2rem] bg-white shadow-lift ring-1 ring-stone-200/70 lg:grid-cols-2">
            <div class="p-7 sm:p-10">
                <h1 class="text-2xl font-extrabold tracking-tight text-cocoa sm:text-3xl">{{ title }}</h1>
                <p v-if="subtitle" class="mt-2 text-stone-500">{{ subtitle }}</p>
                <div class="mt-8"><slot /></div>
            </div>
            <div class="relative hidden overflow-hidden bg-cocoa lg:block">
                <div class="absolute inset-0 bg-[radial-gradient(30rem_20rem_at_20%_10%,var(--color-brand-500),transparent),radial-gradient(26rem_20rem_at_90%_90%,var(--color-brand-800),transparent)]" />
                <div class="relative flex h-full flex-col justify-between p-10 text-white">
                    <img v-if="page.props.site.logo" :src="page.props.site.logo" alt="" class="size-20 rounded-2xl bg-white/95 object-contain p-2" />
                    <div>
                        <p class="text-3xl font-extrabold leading-tight">{{ page.props.site.name }}</p>
                        <p class="mt-3 text-white/75">{{ page.props.site.tagline }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
