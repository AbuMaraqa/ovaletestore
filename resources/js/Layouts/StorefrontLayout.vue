<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';
import FlashToast from '@/Components/Common/FlashToast.vue';
import PageLoader from '@/Components/Common/PageLoader.vue';
import SiteFooter from '@/Components/Storefront/SiteFooter.vue';
import SiteHeader from '@/Components/Storefront/SiteHeader.vue';
import WhatsAppButton from '@/Components/Storefront/WhatsAppButton.vue';
import { startSmoothScroll, stopSmoothScroll, syncScrollWithNavigation } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';

const { t } = useTrans();

// Persistent layout: Lenis lives for the whole storefront session and is kept in step with every page swap
// (new pages open at the top even if a smooth scroll was still easing when the link was clicked).
let off: (() => void) | undefined;
onMounted(() => {
    startSmoothScroll();
    off = syncScrollWithNavigation();
});
onBeforeUnmount(() => {
    off?.();
    stopSmoothScroll();
});
</script>

<template>
    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[200] focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:shadow"
    >
        {{ t('common.skip_to_content') }}
    </a>
    <PageLoader content="#main" />
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
