<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import SocialIcon from '@/Components/Storefront/SocialIcon.vue';
import StoreLocations from '@/Components/Storefront/StoreLocations.vue';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps & { nav_categories: { id: number; name: string; url: string }[] }>();
const { t } = useTrans();
const site = computed(() => page.props.site);
const categories = computed(() => (page.props.nav_categories ?? []).slice(0, 8));
const phoneLines = computed(() => (site.value.phone_lines?.length ? site.value.phone_lines : site.value.phone ? [{ number: site.value.phone, label: null }] : []));
/** "+972593387610" → "0593387610" (how customers here dial and read it). */
const display = (number: string) => number.replace(/^\+97[02]/, '0');
/** The contact page shows the branches itself: no second map in its footer. */
const showLocations = computed(() => (site.value.locations?.length ?? 0) > 0 && page.component !== 'Storefront/Pages/Contact');
const year = new Date().getFullYear();
</script>

<template>
    <footer class="mt-24 bg-cocoa text-stone-300">
        <div class="container-store grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-12">
            <div class="lg:col-span-3">
                <p class="text-xl font-bold text-white">{{ site.name }}</p>
                <p class="mt-3 max-w-xs text-sm leading-relaxed text-stone-300">{{ t('storefront.footer_about') }}</p>
                <div v-if="Object.keys(site.social ?? {}).length" class="mt-5 flex flex-wrap gap-2">
                    <a
                        v-for="(url, network) in site.social"
                        :key="network"
                        :href="url"
                        target="_blank"
                        rel="noopener"
                        :aria-label="String(network)"
                        class="inline-flex size-10 items-center justify-center rounded-xl bg-white/10 text-[#e4cfa3] transition hover:bg-white/20 hover:text-white"
                    >
                        <SocialIcon :name="String(network)" class="size-[18px]" />
                    </a>
                </div>
            </div>

            <nav class="lg:col-span-4" :aria-label="t('storefront.categories')">
                <p class="text-sm font-bold text-white">{{ t('storefront.categories') }}</p>
                <ul class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                    <li v-for="category in categories" :key="category.id">
                        <Link :href="category.url" class="transition hover:text-white">{{ category.name }}</Link>
                    </li>
                    <li><Link :href="route('storefront.shop')" class="font-semibold text-[#e4cfa3] hover:text-white">{{ t('storefront.shop') }}</Link></li>
                </ul>
            </nav>

            <div class="sm:col-span-2 lg:col-span-5">
                <p class="text-sm font-bold text-white">{{ t('storefront.footer_contact') }}</p>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    <li v-for="line in phoneLines" :key="line.number">
                        <a :href="`tel:${line.number}`" class="group flex items-start gap-3 rounded-2xl p-3 ring-1 ring-white/10 transition hover:bg-white/5">
                            <SocialIcon name="phone" class="mt-0.5 size-4 shrink-0 text-[#e4cfa3]" />
                            <span class="min-w-0">
                                <span v-if="line.label" class="block text-[13px] leading-snug text-stone-300">{{ line.label }}</span>
                                <span class="mt-0.5 block font-bold text-white tabular-nums" dir="ltr">{{ display(line.number) }}</span>
                            </span>
                        </a>
                    </li>
                    <li v-if="site.whatsapp">
                        <a :href="`https://wa.me/${site.whatsapp}`" target="_blank" rel="noopener" class="flex h-full items-center gap-3 rounded-2xl bg-[#128C7E] p-3 font-bold text-white transition hover:bg-[#0f7a6e]">
                            <SocialIcon name="whatsapp" class="size-5 shrink-0" />
                            {{ t('common.whatsapp_us') }}
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div v-if="showLocations" class="container-store pb-14">
            <p class="mb-4 text-sm font-bold text-white">{{ t('storefront.our_branches') }}</p>
            <StoreLocations :locations="site.locations" tone="dark" />
        </div>

        <div class="border-t border-white/10">
            <p class="container-store py-5 text-xs text-stone-400">© {{ year }} {{ site.name }}. {{ t('storefront.rights') }}</p>
        </div>
    </footer>
</template>
