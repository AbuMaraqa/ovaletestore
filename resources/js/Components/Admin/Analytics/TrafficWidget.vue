<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import TrafficChart from '@/Components/Admin/Analytics/TrafficChart.vue';
import type { AnalyticsReport } from '@/Components/Admin/Analytics/types';
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/** Dashboard card: GA4 visitors / page views of the last 7 days + visitors right now (SiteAnalytics::summary()). */
defineProps<{ traffic: AnalyticsReport }>();
const { t } = useTrans();
const fmt = (n: number) => new Intl.NumberFormat(undefined).format(n);
</script>

<template>
    <KtCard :title="`${t('analytics.title')} · ${t('analytics.period', { days: 7 })}`">
        <template #toolbar>
            <Link :href="route('admin.analytics.index')" class="kt-btn kt-btn-sm kt-btn-outline">{{ t('analytics.open') }}</Link>
        </template>

        <div v-if="traffic.status === 'ok' && traffic.kpis" class="grid items-center gap-6 md:grid-cols-[auto_1fr]">
            <dl class="grid grid-cols-3 gap-6 md:grid-cols-1 md:gap-4">
                <div>
                    <dt class="text-xs text-secondary-foreground">{{ t('analytics.visitors') }}</dt>
                    <dd class="text-2xl font-semibold text-mono tabular-nums"><bdi dir="ltr">{{ fmt(traffic.kpis.visitors) }}</bdi></dd>
                </div>
                <div>
                    <dt class="text-xs text-secondary-foreground">{{ t('analytics.views') }}</dt>
                    <dd class="text-2xl font-semibold text-mono tabular-nums"><bdi dir="ltr">{{ fmt(traffic.kpis.views) }}</bdi></dd>
                </div>
                <div v-if="traffic.realtime !== null && traffic.realtime !== undefined">
                    <dt class="flex items-center gap-1.5 text-xs text-secondary-foreground"><span class="size-2 rounded-full bg-success" /> {{ t('analytics.realtime') }}</dt>
                    <dd class="text-2xl font-semibold text-mono tabular-nums">{{ traffic.realtime }}</dd>
                </div>
            </dl>
            <TrafficChart v-if="traffic.trend?.length" :points="traffic.trend" compact />
        </div>

        <p v-else-if="traffic.status === 'error'" class="flex items-center gap-2 text-sm text-destructive"><KtIcon name="information-2" /> {{ t('analytics.error') }}</p>
        <p v-else class="flex items-center gap-2 text-sm text-secondary-foreground">
            <KtIcon name="chart-simple" class="text-primary" /> {{ t('analytics.not_connected') }}
        </p>
    </KtCard>
</template>
