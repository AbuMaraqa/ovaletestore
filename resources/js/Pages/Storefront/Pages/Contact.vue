<script setup lang="ts">
import SocialIcon from '@/Components/Storefront/SocialIcon.vue';
import StoreLocations from '@/Components/Storefront/StoreLocations.vue';
import { useTrans } from '@/Composables/useTrans';
import type { PhoneLine, StoreLocation } from '@/types';

defineProps<{
    contact: { phone_lines: PhoneLine[]; whatsapp: string | null; email: string | null; locations: StoreLocation[]; social: Record<string, string> };
}>();
const { t } = useTrans();
const display = (number: string) => number.replace(/^\+97[02]/, '0');
</script>

<template>
    <div>
        <div class="border-b border-stone-200/70 bg-gradient-to-b from-brand-50 to-cream">
            <div class="container-store py-12 text-center">
                <h1 class="text-3xl font-extrabold text-cocoa sm:text-5xl">{{ t('storefront.contact_us') }}</h1>
                <p class="mx-auto mt-4 max-w-xl text-lg text-stone-600">{{ t('storefront.contact_intro') }}</p>
            </div>
        </div>

        <div class="container-store grid gap-5 py-12 md:grid-cols-3">
            <a v-if="contact.whatsapp" :href="`https://wa.me/${contact.whatsapp}`" target="_blank" rel="noopener" class="group rounded-3xl bg-whatsapp p-7 text-white transition hover:-translate-y-1 hover:shadow-lift">
                <SocialIcon name="whatsapp" class="size-10" />
                <p class="mt-5 text-xl font-bold">{{ t('common.whatsapp_us') }}</p>
                <p class="mt-1 text-white/90" dir="ltr">+{{ contact.whatsapp }}</p>
            </a>

            <div class="rounded-3xl bg-white p-7 ring-1 ring-stone-200/70">
                <SocialIcon name="phone" class="size-9 text-brand-600" />
                <p class="mt-5 text-xl font-bold text-cocoa">{{ t('storefront.call_us') }}</p>
                <ul class="mt-3 space-y-3">
                    <li v-for="line in contact.phone_lines" :key="line.number">
                        <span v-if="line.label" class="block text-sm text-stone-600">{{ line.label }}</span>
                        <a :href="`tel:${line.number}`" class="font-bold text-cocoa tabular-nums hover:text-brand-700" dir="ltr">{{ display(line.number) }}</a>
                    </li>
                </ul>
            </div>

            <div class="rounded-3xl bg-cocoa p-7 text-white">
                <p class="text-xl font-bold">{{ t('storefront.follow_us') }}</p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a
                        v-for="(url, network) in contact.social"
                        :key="network"
                        :href="url"
                        target="_blank"
                        rel="noopener"
                        :aria-label="String(network)"
                        class="inline-flex size-12 items-center justify-center rounded-2xl bg-white/10 text-[#e4cfa3] transition hover:bg-white/20 hover:text-white"
                    >
                        <SocialIcon :name="String(network)" class="size-5" />
                    </a>
                </div>
                <p v-if="contact.email" class="mt-6 text-white/80"><a :href="`mailto:${contact.email}`" class="hover:text-white">{{ contact.email }}</a></p>
            </div>
        </div>

        <section v-if="contact.locations.length" class="container-store pb-16">
            <h2 class="mb-5 text-2xl font-extrabold text-cocoa">{{ t('storefront.our_branches') }}</h2>
            <StoreLocations :locations="contact.locations" />
        </section>
    </div>
</template>
