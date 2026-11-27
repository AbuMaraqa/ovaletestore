<script setup lang="ts">
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/**
 * Shown until the reports are connected. Plain language only: the server setup (GA4 Data API credentials) is done
 * by the developer and documented in documentation/seo-implementation.md — never shown to the store owner.
 */
const { t } = useTrans();
</script>

<template>
    <KtCard>
        <div class="flex flex-col items-center gap-3 py-10 text-center">
            <span class="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-2xl text-primary"><KtIcon name="chart-simple" /></span>
            <p class="text-base font-semibold text-mono">{{ t('analytics.not_connected') }}</p>
            <p class="max-w-md text-sm text-secondary-foreground">{{ t('analytics.not_connected_help') }}</p>
        </div>
    </KtCard>
</template>
