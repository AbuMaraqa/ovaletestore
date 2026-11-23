<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps & { nav_categories: { id: number; name: string; url: string }[] }>();
const { t } = useTrans();
const site = computed(() => page.props.site);
const categories = computed(() => (page.props.nav_categories ?? []).slice(0, 8));
const year = new Date().getFullYear();
</script>

<template>
    <footer class="mt-24 bg-cocoa text-stone-300">
        <div class="container-store grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-1">
                <p class="text-xl font-bold text-white">{{ site.name }}</p>
                <p class="mt-3 max-w-xs text-sm leading-relaxed text-stone-400">{{ t('storefront.footer_about') }}</p>
            </div>
            <div class="lg:col-span-2">
                <p class="text-sm font-semibold text-white">{{ t('storefront.categories') }}</p>
                <ul class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                    <li v-for="category in categories" :key="category.id">
                        <Link :href="category.url" class="transition hover:text-white">{{ category.name }}</Link>
                    </li>
                </ul>
            </div>
            <div>
                <p class="text-sm font-semibold text-white">{{ t('storefront.footer_contact') }}</p>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li v-if="site.phone"><a :href="`tel:${site.phone}`" class="hover:text-white" dir="ltr">{{ site.phone }}</a></li>
                    <li v-if="site.whatsapp">
                        <a :href="`https://wa.me/${site.whatsapp}`" target="_blank" rel="noopener" class="hover:text-white">{{ t('common.whatsapp_us') }}</a>
                    </li>
                    <li><Link :href="route('storefront.shop')" class="hover:text-white">{{ t('storefront.shop') }}</Link></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10">
            <p class="container-store py-5 text-xs text-stone-400">© {{ year }} {{ site.name }}. {{ t('storefront.rights') }}</p>
        </div>
    </footer>
</template>
