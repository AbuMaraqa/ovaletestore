<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { revealOnScroll, useGsap } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';

defineProps<{ brands: { id: number; name: string; url: string; logo: string | null; products_count: number }[] }>();
const { t } = useTrans();
const root = ref<HTMLElement | null>(null);
useGsap(root, ({ root: el }) => revealOnScroll(el));
</script>

<template>
    <div ref="root">
        <div class="border-b border-stone-200/70 bg-gradient-to-b from-brand-50 to-cream">
            <div class="container-store py-10 text-center">
                <h1 class="text-3xl font-extrabold tracking-tight text-cocoa sm:text-4xl">{{ t('storefront.brands_title') }}</h1>
                <p class="mx-auto mt-3 max-w-xl text-stone-500">{{ t('storefront.brands_subtitle') }}</p>
            </div>
        </div>
        <div class="container-store py-10">
            <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                <li v-for="brand in brands" :key="brand.id" data-reveal>
                    <Link :href="brand.url" class="group flex h-full flex-col items-center rounded-3xl bg-white p-5 text-center ring-1 ring-stone-200/70 transition hover:-translate-y-1 hover:shadow-lift">
                        <span class="flex h-24 w-full items-center justify-center">
                            <img v-if="brand.logo" :src="brand.logo" :alt="brand.name" class="max-h-full w-auto object-contain mix-blend-multiply transition group-hover:scale-105" loading="lazy" />
                            <span v-else class="text-xl font-extrabold text-cocoa">{{ brand.name }}</span>
                        </span>
                        <span class="mt-4 font-bold text-cocoa">{{ brand.name }}</span>
                        <span class="mt-0.5 text-xs text-stone-400">{{ t('storefront.products_count', { count: brand.products_count }) }}</span>
                    </Link>
                </li>
            </ul>
        </div>
    </div>
</template>
