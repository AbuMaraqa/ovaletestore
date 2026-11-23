<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { stickyBarVisible } from '@/Composables/useCountdown';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
const { t } = useTrans();
const number = computed(() => page.props.site.whatsapp);
</script>

<template>
    <a
        v-if="number"
        :href="`https://wa.me/${number}`"
        target="_blank"
        rel="noopener"
        :aria-label="t('common.whatsapp_us')"
        class="fixed end-5 z-30 inline-flex size-14 items-center justify-center rounded-full bg-[#128C7E] text-white shadow-lift ring-2 ring-white transition-all hover:scale-105"
        :class="stickyBarVisible ? 'bottom-24' : 'bottom-5'"
    >
        <svg viewBox="0 0 24 24" class="size-7" fill="currentColor" aria-hidden="true">
            <path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.3.5-.4.4c-.1.1-.3.3-.1.6.2.3.8 1.3 1.6 2.1 1.1 1 2 1.3 2.3 1.4.3.1.4.1.6-.1l.8-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.4.2.5.3.1.2.1.7-.2 1.2Z" />
        </svg>
    </a>
</template>
