<script setup lang="ts">
import { Deferred, Link } from '@inertiajs/vue3';
import AnalyticsReport from '@/Components/Admin/Analytics/AnalyticsReport.vue';
import AnalyticsSetup from '@/Components/Admin/Analytics/AnalyticsSetup.vue';
import type { AnalyticsReport as Report } from '@/Components/Admin/Analytics/types';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

defineProps<{
    days: number;
    periods: number[];
    configured: boolean;
    tracking: { measurement_id: string | null; enabled: boolean };
    can_edit_settings: boolean;
    report?: Report;
    realtime?: number | null;
}>();
const { t } = useTrans();
</script>

<template>
    <PageHeader :title="t('analytics.title')" :description="t('analytics.subtitle')">
        <template #actions>
            <nav class="flex gap-1" :aria-label="t('analytics.title')">
                <Link
                    v-for="p in periods"
                    :key="p"
                    :href="route('admin.analytics.index', { days: p })"
                    :class="['kt-btn kt-btn-sm', p === days ? 'kt-btn-primary' : 'kt-btn-outline']"
                    preserve-scroll
                >
                    {{ t('analytics.period', { days: p }) }}
                </Link>
            </nav>
        </template>
    </PageHeader>

    <div class="mb-5 flex flex-wrap items-center gap-3">
        <!-- Visitors right now (GA4 realtime, last 30 minutes) -->
        <Deferred v-if="configured" data="realtime">
            <template #fallback><span class="h-9 w-40 animate-pulse rounded-full bg-accent" /></template>
            <span v-if="realtime !== null && realtime !== undefined" class="kt-badge kt-badge-lg kt-badge-success kt-badge-outline gap-2" :title="t('analytics.realtime_help')">
                <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-60" /><span class="relative size-2 rounded-full bg-success" /></span>
                {{ t('analytics.realtime') }}: <bdi class="font-semibold tabular-nums">{{ realtime }}</bdi>
            </span>
        </Deferred>
        <span v-if="tracking.measurement_id && tracking.enabled" class="kt-badge kt-badge-lg kt-badge-outline kt-badge-primary gap-1.5">
            <KtIcon name="check-circle" /> {{ t('analytics.tracking_on', { id: tracking.measurement_id }) }}
        </span>
        <span v-else class="kt-badge kt-badge-lg kt-badge-outline kt-badge-warning gap-1.5" :title="t('analytics.tracking_off_help')">
            <KtIcon name="information-2" /> {{ t('analytics.tracking_off') }}
            <Link v-if="can_edit_settings" :href="route('admin.settings.edit', 'seo')" class="underline">{{ t('admin.settings') }}</Link>
        </span>
    </div>

    <AnalyticsSetup v-if="!configured" />

    <Deferred v-else data="report">
        <template #fallback>
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6"><div v-for="i in 6" :key="i" class="h-28 animate-pulse rounded-xl bg-accent" /></div>
            <div class="mt-5 h-80 animate-pulse rounded-xl bg-accent" />
        </template>
        <div v-if="report?.status === 'error'" class="kt-alert kt-alert-destructive" role="alert">
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
