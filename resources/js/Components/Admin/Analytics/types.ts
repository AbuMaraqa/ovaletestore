import type { TrafficPoint } from './TrafficChart.vue';

/** Shape of App\Support\Analytics\SiteAnalytics::report() / summary(). */
export interface AnalyticsKpis {
    visitors: number;
    views: number;
    sessions: number;
    engagement_rate: number;
    avg_duration: number;
    bounce_rate: number;
}

export interface AnalyticsReport {
    status: 'ok' | 'error' | 'not_configured';
    source?: 'google' | 'internal';
    message?: string;
    kpis?: AnalyticsKpis;
    trend?: TrafficPoint[];
    pages?: { title: string; url: string; views: number }[];
    page_visitors?: { title: string; visitors: number; views: number }[];
    recent?: { date: string; title: string; visitors: number; views: number }[];
    referrers?: { label: string; views: number }[];
    user_types?: { label: 'new' | 'returning'; visitors: number }[];
    countries?: { label: string; views: number }[];
    browsers?: { label: string; views: number }[];
    systems?: { label: string; views: number }[];
    channels?: { label: string; value: number }[];
    devices?: { label: string; value: number }[];
    realtime?: number | null;
}
