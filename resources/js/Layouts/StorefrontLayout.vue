<script setup lang="ts">
import FlashToast from '@/Components/Common/FlashToast.vue';
import SeoHead from '@/Components/Common/SeoHead.vue';
import SiteFooter from '@/Components/Storefront/SiteFooter.vue';
import SiteHeader from '@/Components/Storefront/SiteHeader.vue';
import WhatsAppButton from '@/Components/Storefront/WhatsAppButton.vue';
import { useTrans } from '@/Composables/useTrans';

const { t } = useTrans();
</script>

<template>
    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[200] focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:shadow"
    >
        {{ t('common.skip_to_content') }}
    </a>
    <SeoHead />
    <div class="flex min-h-screen flex-col">
        <SiteHeader />
        <main id="main" class="flex-1">
            <slot />
        </main>
        <SiteFooter />
    </div>
    <WhatsAppButton />
    <FlashToast />
</template>
