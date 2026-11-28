<script setup lang="ts">
import { computed } from 'vue';
import RankList from '@/Components/Admin/Analytics/RankList.vue';
import TrafficChart from '@/Components/Admin/Analytics/TrafficChart.vue';
import type { AnalyticsReport } from '@/Components/Admin/Analytics/types';
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/**
 * The traffic report (Google Analytics via SiteAnalytics, or the store counter via StoreVisits): KPIs, daily trend,
 * pages, sources, audience and technology. Lists a source does not provide (countries, new vs returning for the
 * store counter) are left out.
 */
const props = defineProps<{ report: AnalyticsReport }>();
const { t } = useTrans();
const fmt = (n: number) => new Intl.NumberFormat(undefined).format(n);
const duration = (s: number) => (s < 60 ? t('analytics.seconds', { count: s }) : t('analytics.minutes', { m: Math.floor(s / 60), s: s % 60 }));
const known = (group: string, label: string) => {
    const key = `analytics.${group}.${label}`;
    const text = t(key);
    return text === key ? label : text;
};

const kpis = computed(() => {
    const k = props.report.kpis;
    if (!k) return [];
    return [
        { key: 'visitors', icon: 'people', value: fmt(k.visitors) },
        { key: 'views', icon: 'eye', value: fmt(k.views) },
        { key: 'sessions', icon: 'mouse-circle', value: fmt(k.sessions) },
        { key: 'engagement_rate', icon: 'like', value: `${k.engagement_rate}%` },
        { key: 'avg_duration', icon: 'time', value: duration(k.avg_duration) },
        { key: 'bounce_rate', icon: 'exit-right', value: `${k.bounce_rate}%` },
    ];
});
const rows = (list?: { label: string; views: number }[]) => (list ?? []).map((r) => ({ label: r.label, value: r.views }));
const referrers = computed(() => (props.report.referrers ?? []).map((r) => ({ label: r.label || t('analytics.direct'), value: r.views })));
const channels = computed(() => (props.report.channels ?? []).map((r) => ({ label: known('channel_names', r.label), value: r.value })));
const devices = computed(() => (props.report.devices ?? []).map((r) => ({ label: known('device_names', r.label), value: r.value })));
const userTypes = computed(() => (props.report.user_types ?? []).map((r) => ({ label: t(`analytics.${r.label}`), value: r.visitors })));
const pages = computed(() => (props.report.pages ?? []).map((p) => ({ label: p.title || p.url, value: p.views, href: `https://${p.url}` })));
</script>

<template>
    <div class="space-y-5">
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6">
            <div v-for="k in kpis" :key="k.key" class="kt-card">
                <div class="kt-card-content flex flex-col gap-3">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-lg text-primary"><KtIcon :name="k.icon" /></span>
                    <div>
                        <p class="text-xl font-semibold leading-none text-mono tabular-nums"><bdi dir="ltr">{{ k.value }}</bdi></p>
                        <p class="mt-2 text-sm text-secondary-foreground">{{ t(`analytics.${k.key}`) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <KtCard :title="t('analytics.trend')">
            <TrafficChart v-if="report.trend?.length" :points="report.trend" />
        </KtCard>

        <div class="grid gap-5 lg:grid-cols-2">
            <RankList :title="t('analytics.top_pages')" :rows="pages" />
            <KtCard :title="t('analytics.page_visitors')" flush>
                <table class="kt-table">
                    <thead>
                        <tr><th>{{ t('analytics.page') }}</th><th class="text-end">{{ t('analytics.visitors') }}</th><th class="text-end">{{ t('analytics.views') }}</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in report.page_visitors" :key="p.title">
                            <td class="max-w-64 truncate">{{ p.title }}</td>
                            <td class="text-end tabular-nums">{{ fmt(p.visitors) }}</td>
                            <td class="text-end tabular-nums">{{ fmt(p.views) }}</td>
                        </tr>
                        <tr v-if="!report.page_visitors?.length"><td colspan="3" class="text-muted-foreground">{{ t('analytics.no_data') }}</td></tr>
                    </tbody>
                </table>
            </KtCard>
        </div>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <RankList :title="t('analytics.channels')" :rows="channels" />
            <RankList :title="t('analytics.referrers')" :rows="referrers" />
            <RankList v-if="report.user_types" :title="t('analytics.user_types')" :rows="userTypes" />
            <RankList v-if="report.countries" :title="t('analytics.countries')" :rows="rows(report.countries)" />
            <RankList :title="t('analytics.devices')" :rows="devices" />
            <RankList :title="t('analytics.browsers')" :rows="rows(report.browsers)" />
            <RankList :title="t('analytics.systems')" :rows="rows(report.systems)" />
        </div>

        <KtCard :title="t('analytics.recent')" flush>
            <table class="kt-table">
                <thead>
                    <tr>
                        <th>{{ t('analytics.date') }}</th><th>{{ t('analytics.page') }}</th>
                        <th class="text-end">{{ t('analytics.visitors') }}</th><th class="text-end">{{ t('analytics.views') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(r, i) in report.recent" :key="i">
                        <td class="whitespace-nowrap tabular-nums" dir="ltr">{{ r.date }}</td>
                        <td class="max-w-96 truncate">{{ r.title }}</td>
                        <td class="text-end tabular-nums">{{ fmt(r.visitors) }}</td>
                        <td class="text-end tabular-nums">{{ fmt(r.views) }}</td>
                    </tr>
                    <tr v-if="!report.recent?.length"><td colspan="4" class="text-muted-foreground">{{ t('analytics.no_data') }}</td></tr>
                </tbody>
            </table>
        </KtCard>
    </div>
</template>
