<script setup lang="ts">
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/** Shown until GA4 Data API credentials are installed on the server (nothing secret is sent to the browser). */
defineProps<{ credentialsPath: string }>();
const { t } = useTrans();
</script>

<template>
    <KtCard :title="t('analytics.setup_title')">
        <div class="flex gap-4">
            <span class="hidden size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-2xl text-primary sm:flex"><KtIcon name="chart-simple" /></span>
            <div class="min-w-0 space-y-3 text-sm">
                <p class="text-secondary-foreground">{{ t('analytics.setup_intro') }}</p>
                <ol class="list-decimal space-y-2 ps-5">
                    <li v-for="i in 5" :key="i">{{ t(`analytics.setup_steps.${i - 1}`) }}</li>
                </ol>
                <code class="block overflow-x-auto rounded-lg bg-accent px-3 py-2 text-xs" dir="ltr">{{ credentialsPath }}</code>
                <code class="block overflow-x-auto rounded-lg bg-accent px-3 py-2 text-xs" dir="ltr">ANALYTICS_PROPERTY_ID=123456789</code>
            </div>
        </div>
    </KtCard>
</template>
