<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import SocialIcon from '@/Components/Storefront/SocialIcon.vue';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

/** Reassurance under the buy button: cash on delivery, delivery areas, and a prefilled WhatsApp question. */
const props = defineProps<{ productName: string }>();
const page = usePage<SharedProps>();
const { t } = useTrans();

const whatsappUrl = computed(() => {
    const number = page.props.site.whatsapp;
    if (!number) return null;
    const url = typeof window !== 'undefined' ? window.location.href.split('?')[0] : '';
    const text = t('storefront.whatsapp_product_message', { product: props.productName, url });
    return `https://wa.me/${number}?text=${encodeURIComponent(text)}`;
});
</script>

<template>
    <div class="mt-6 space-y-3">
        <ul class="grid gap-2 text-sm sm:grid-cols-2">
            <li class="flex items-center gap-2.5 rounded-xl bg-white px-4 py-3 ring-1 ring-stone-100">
                <Icon name="shield" class="size-5 shrink-0 text-brand-700" />
                <span class="font-medium text-stone-700">{{ t('storefront.trust_cod') }}</span>
            </li>
            <li class="flex items-center gap-2.5 rounded-xl bg-white px-4 py-3 ring-1 ring-stone-100">
                <Icon name="truck" class="size-5 shrink-0 text-brand-700" />
                <span class="font-medium text-stone-700">{{ t('storefront.trust_delivery') }}</span>
            </li>
        </ul>
        <a
            v-if="whatsappUrl"
            :href="whatsappUrl"
            target="_blank"
            rel="noopener"
            class="flex items-center justify-center gap-2 rounded-full bg-whatsapp px-5 py-3 text-sm font-bold text-white transition hover:bg-whatsapp-dark"
        >
            <SocialIcon name="whatsapp" class="size-5" />
            {{ t('storefront.ask_whatsapp') }}
        </a>
    </div>
</template>
