<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { revealOnScroll, useGsap } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';

const { t } = useTrans();
const root = ref<HTMLElement | null>(null);
useGsap(root, ({ root: el }) => revealOnScroll(el));
const values = ['global', 'quality', 'offers', 'secure'] as const;
</script>

<template>
    <div ref="root">
        <section class="bg-cocoa text-white">
            <div class="container-store py-16 lg:py-24">
                <p data-reveal class="text-sm font-bold text-brand-300">{{ t('storefront.our_story') }}</p>
                <h1 data-reveal class="mt-4 max-w-3xl text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">{{ t('storefront.about_title') }}</h1>
                <p data-reveal class="mt-6 max-w-2xl text-lg leading-relaxed text-white/75">{{ t('storefront.about_lead') }}</p>
            </div>
        </section>
        <section class="container-store py-16">
            <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <li v-for="key in values" :key="key" data-reveal class="rounded-3xl bg-white p-6 ring-1 ring-stone-200/70">
                    <p class="text-lg font-bold text-cocoa">{{ t(`storefront.usp_${key}`) }}</p>
                    <p class="mt-2 text-sm leading-relaxed text-stone-500">{{ t(`storefront.usp_${key}_text`) }}</p>
                </li>
            </ul>
            <div data-reveal class="mt-12 flex flex-wrap justify-center gap-3">
                <Link :href="route('storefront.shop')" class="btn-primary h-12 px-8">{{ t('storefront.hero_cta') }}</Link>
                <Link :href="route('storefront.contact')" class="btn-ghost h-12 px-8">{{ t('storefront.contact_us') }}</Link>
            </div>
        </section>
    </div>
</template>
