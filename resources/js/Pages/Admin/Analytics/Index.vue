<script setup lang="ts">
import { Deferred, Link } from '@inertiajs/vue3';
import AnalyticsReport from '@/Components/Admin/Analytics/AnalyticsReport.vue';
import type { AnalyticsReport as Report } from '@/Components/Admin/Analytics/types';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

/**
 * Site traffic, two sources side by side: the store's own counter (always on) and Google Analytics (once connected).
 * Source and period are both in the URL, so each view can be bookmarked or shared.
 */
const props = defineProps<{
    days: number;
    periods: number[];
    source: 'internal' | 'google';
    google_connected: boolean;
    tracking: { measurement_id: string | null; enabled: boolean };
    can_edit_settings: boolean;
    report?: Report;
    realtime?: number | null;
}>();
const { t } = useTrans();

const sources = [
    { key: 'internal', icon: 'chart-simple' },
    { key: 'google', icon: 'google' },
] as const;
const href = (query: { days?: number; source?: string }) => route('admin.analytics.index', { days: props.days, source: props.source, ...query });
</script>

<template>
    <PageHeader :title="t('analytics.title')" :description="t(`analytics.source_${source}_help`)">
        <template #actions>
            <nav class="flex gap-1" :aria-label="t('analytics.title')">
                <Link
                    v-for="p in periods"
                    :key="p"
                    :href="href({ days: p })"
                    :class="['kt-btn kt-btn-sm', p === days ? 'kt-btn-primary' : 'kt-btn-outline']"
                    preserve-scroll
                >
                    {{ t('analytics.period', { days: p }) }}
                </Link>
            </nav>
        </template>
    </PageHeader>

    <!-- Source switch -->
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <nav class="inline-flex gap-1 rounded-lg bg-muted p-1" :aria-label="t('analytics.source')">
            <Link
                v-for="s in sources"
                :key="s.key"
                :href="href({ source: s.key })"
                preserve-scroll
                :aria-current="s.key === source ? 'page' : undefined"
                :class="[
                    'inline-flex h-9 items-center gap-2 rounded-md px-4 text-sm font-medium transition',
                    s.key === source ? 'bg-background text-foreground shadow-xs' : 'text-secondary-foreground hover:text-foreground',
                ]"
            >
                <KtIcon :name="s.icon" />
                {{ t(`analytics.source_${s.key}`) }}
                <span v-if="s.key === 'google' && !google_connected" class="size-1.5 rounded-full bg-warning" :title="t('analytics.not_connected')" />
            </Link>
        </nav>

        <div class="flex flex-wrap items-center gap-3">
            <!-- Visitors right now (last 30 minutes) -->
            <Deferred data="realtime">
                <template #fallback><span class="h-9 w-40 animate-pulse rounded-full bg-accent" /></template>
                <span v-if="realtime !== null && realtime !== undefined" class="kt-badge kt-badge-lg kt-badge-success kt-badge-outline gap-2" :title="t('analytics.realtime_help')">
                    <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-60" /><span class="relative size-2 rounded-full bg-success" /></span>
                    {{ t('analytics.realtime') }}: <bdi class="font-semibold tabular-nums">{{ realtime }}</bdi>
                </span>
            </Deferred>
            <template v-if="source === 'google'">
                <span v-if="tracking.measurement_id && tracking.enabled" class="kt-badge kt-badge-lg kt-badge-outline kt-badge-primary gap-1.5">
                    <KtIcon name="check-circle" /> {{ t('analytics.tracking_on', { id: tracking.measurement_id }) }}
                </span>
                <span v-else class="kt-badge kt-badge-lg kt-badge-outline kt-badge-warning gap-1.5" :title="t('analytics.tracking_off_help')">
                    <KtIcon name="information-2" /> {{ t('analytics.tracking_off') }}
                    <Link v-if="can_edit_settings" :href="route('admin.settings.edit', 'seo')" class="underline">{{ t('admin.settings') }}</Link>
                </span>
            </template>
        </div>
    </div>

    <Deferred data="report">
        <template #fallback>
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6"><div v-for="i in 6" :key="i" class="h-28 animate-pulse rounded-xl bg-accent" /></div>
            <div class="mt-5 h-80 animate-pulse rounded-xl bg-accent" />
        </template>
        <!-- Google Analytics reports not connected yet: a plain note (setup is the developer's job, never shown here). -->
        <div v-if="report?.status === 'not_configured'" class="kt-card">
            <div class="kt-card-content flex flex-col items-center gap-3 py-12 text-center">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-2xl text-primary"><KtIcon name="google" /></span>
                <p class="font-semibold text-mono">{{ t('analytics.not_connected') }}</p>
                <p class="max-w-md text-sm text-secondary-foreground">{{ t('analytics.not_connected_help') }}</p>
                <Link :href="href({ source: 'internal' })" class="kt-btn kt-btn-outline kt-btn-sm mt-2"><KtIcon name="chart-simple" /> {{ t('analytics.source_internal') }}</Link>
            </div>
        </div>
        <div v-else-if="report?.status === 'error'" class="kt-alert kt-alert-destructive" role="alert">
            <KtIcon name="information-2" />
            <div>
                <p class="font-medium">{{ t('analytics.error') }}</p>
                <!-- Google's technical message stays in the server log (SiteAnalytics reports it). -->
                <p class="text-sm opacity-80">{{ t('analytics.error_help') }}</p>
            </div>
        </div>
        <AnalyticsReport v-else-if="report?.status === 'ok'" :report="report" />
    </Deferred>
</template>
