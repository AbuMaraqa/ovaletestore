<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import FlashToast from '@/Components/Common/FlashToast.vue';
import PageLoader from '@/Components/Common/PageLoader.vue';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
</script>

<template>
    <PageLoader content="#auth-page" />
    <div id="auth-page" class="grid min-h-screen lg:grid-cols-2">
        <div class="flex items-center justify-center p-6 sm:p-10">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex items-center gap-2">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-primary font-bold text-primary-foreground">O</span>
                    <span class="text-lg font-semibold text-mono">{{ page.props.site.name }}</span>
                </div>
                <slot />
                <div class="mt-10 flex gap-2 text-xs">
                    <a v-for="l in page.props.locale.available" :key="l.code" :href="l.url" :lang="l.code" class="kt-btn kt-btn-sm kt-btn-ghost">{{ l.native }}</a>
                </div>
            </div>
        </div>
        <div class="relative hidden overflow-hidden bg-[radial-gradient(50rem_30rem_at_20%_10%,var(--color-brand-400),transparent),radial-gradient(40rem_30rem_at_90%_90%,var(--color-brand-800),transparent)] bg-cocoa lg:block">
            <div class="absolute inset-x-12 bottom-12 text-white">
                <p class="text-3xl font-bold leading-tight">{{ page.props.site.name }}</p>
                <p class="mt-3 max-w-md text-white/75">{{ page.props.site.tagline }}</p>
            </div>
        </div>
    </div>
    <FlashToast />
</template>
