<?php

namespace App\Support\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Analytics\Analytics as AnalyticsService;
use Spatie\Analytics\AnalyticsClient;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\OrderBy;
use Spatie\Analytics\Period;
use Throwable;

/**
 * Google Analytics 4 reports for the admin, through spatie/laravel-analytics (GA4 Data API).
 *
 * Uses every report the package ships (totals by date, visitors & page views per page and per day, most visited
 * pages, referrers, new vs returning, browsers, countries, operating systems), its generic get() for KPIs,
 * channels and devices, and getRealtime() for "visitors right now".
 *
 * Never throws: with no credentials the admin sees a setup guide, an API error (revoked key, wrong property)
 * becomes a readable message. Responses are cached by the package (config analytics.cache_lifetime_in_minutes).
 */
class SiteAnalytics
{
    public const PERIODS = [7, 28, 90, 365];

    public static function configured(): bool
    {
        $credentials = config('analytics.service_account_credentials_json');

        return filled(config('analytics.property_id'))
            && (is_array($credentials) || (is_string($credentials) && is_file($credentials)));
    }

    /**
     * The full report for the analytics page.
     *
     * @return array<string, mixed>
     */
    public function report(int $days): array
    {
        $days = in_array($days, self::PERIODS, true) ? $days : 28;
        $period = Period::days($days);

        return $this->guard(fn () => [
            'kpis' => $this->kpis($period),
            'trend' => $this->trend($period, $days),
            'pages' => Analytics::fetchMostVisitedPages($period, 10)->map(fn (array $r) => [
                'title' => $r['pageTitle'], 'url' => $r['fullPageUrl'], 'views' => (int) $r['screenPageViews'],
            ])->values(),
            'page_visitors' => Analytics::fetchVisitorsAndPageViews($period, 10)->map(fn (array $r) => [
                'title' => $r['pageTitle'], 'visitors' => (int) $r['activeUsers'], 'views' => (int) $r['screenPageViews'],
            ])->sortByDesc('visitors')->values(),
            'recent' => Analytics::fetchVisitorsAndPageViewsByDate(Period::days(min($days, 7)), 12)->map(fn (array $r) => [
                'date' => $this->date($r['date']), 'title' => $r['pageTitle'],
                'visitors' => (int) $r['activeUsers'], 'views' => (int) $r['screenPageViews'],
            ])->values(),
            'referrers' => Analytics::fetchTopReferrers($period, 8)->map(fn (array $r) => [
                'label' => $this->host($r['pageReferrer']), 'views' => (int) $r['screenPageViews'],
            ])->values(),
            'user_types' => Analytics::fetchUserTypes($period)->map(fn (array $r) => [
                'label' => $r['newVsReturning'], 'visitors' => (int) $r['activeUsers'],
            ])->filter(fn (array $r) => in_array($r['label'], ['new', 'returning'], true))->values(),
            'countries' => $this->breakdown(Analytics::fetchTopCountries($period, 8), 'country'),
            'browsers' => $this->breakdown(Analytics::fetchTopBrowsers($period, 6), 'browser'),
            'systems' => $this->breakdown(Analytics::fetchTopOperatingSystems($period, 6), 'operatingSystem'),
            'channels' => $this->dimension($period, 'sessionDefaultChannelGroup', 'sessions', 8),
            'devices' => $this->dimension($period, 'deviceCategory', 'sessions', 4),
        ]);
    }

    /**
     * Small dashboard widget: last 7 days + active visitors now.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $period = Period::days(7);

        return $this->guard(fn () => [
            'kpis' => $this->kpis($period),
            'trend' => $this->trend($period, 7),
            'realtime' => $this->realtime(),
        ]);
    }

    /** Visitors on the site in the last 30 minutes (GA4 realtime), cached 60 s, not for the package's full cache lifetime. */
    public function realtime(): ?int
    {
        if (! self::configured()) {
            return null;
        }

        return Cache::remember('analytics:realtime', 60, function (): ?int {
            try {
                // The package caches every report for the configured lifetime: realtime gets its own uncached client.
                $client = app(AnalyticsClient::class)->setCacheLifeTimeInMinutes(0);
                $service = new AnalyticsService($client, (string) config('analytics.property_id'));

                // Period::toMinuteRange() reads "minutes ago" from the minute-of-hour of the end date, so anchor the
                // window on a full hour: this asks for 29 → 0 minutes ago (GA4's realtime limit for standard properties).
                $anchor = CarbonImmutable::now()->startOfHour();
                $rows = $service->getRealtime(Period::create($anchor->subMinutes(29), $anchor), ['activeUsers']);

                return (int) $rows->sum('activeUsers');
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        });
    }

    /** @return array{visitors: int, views: int, sessions: int, engagement_rate: float, avg_duration: int, bounce_rate: float} */
    private function kpis(Period $period): array
    {
        $row = Analytics::get($period, ['totalUsers', 'screenPageViews', 'sessions', 'engagementRate', 'averageSessionDuration', 'bounceRate'])->first() ?? [];

        return [
            'visitors' => (int) ($row['totalUsers'] ?? 0),
            'views' => (int) ($row['screenPageViews'] ?? 0),
            'sessions' => (int) ($row['sessions'] ?? 0),
            'engagement_rate' => round((float) ($row['engagementRate'] ?? 0) * 100, 1),
            'avg_duration' => (int) round((float) ($row['averageSessionDuration'] ?? 0)),
            'bounce_rate' => round((float) ($row['bounceRate'] ?? 0) * 100, 1),
        ];
    }

    /** One point per day (days without visits included as 0, so the chart has no gaps). @return list<array<string, mixed>> */
    private function trend(Period $period, int $days): array
    {
        $byDay = Analytics::fetchTotalVisitorsAndPageViews($period, $days + 1)
            ->keyBy(fn (array $r) => $this->date($r['date']));

        $points = [];
        foreach (range($days, 0) as $ago) {
            $day = Carbon::today()->subDays($ago);
            $row = $byDay->get($day->toDateString());
            $points[] = [
                'date' => $day->toDateString(),
                'label' => $day->translatedFormat('j M'),
                'visitors' => (int) ($row['activeUsers'] ?? 0),
                'views' => (int) ($row['screenPageViews'] ?? 0),
            ];
        }

        return $points;
    }

    /** @param iterable<int, array<string, mixed>> $rows @return list<array{label: string, views: int}> */
    private function breakdown(iterable $rows, string $dimension): array
    {
        return collect($rows)->map(fn (array $r) => ['label' => (string) $r[$dimension], 'views' => (int) $r['screenPageViews']])->values()->all();
    }

    /** @return list<array{label: string, value: int}> */
    private function dimension(Period $period, string $dimension, string $metric, int $limit): array
    {
        return Analytics::get($period, [$metric], [$dimension], $limit, [OrderBy::metric($metric, true)])
            ->map(fn (array $r) => ['label' => (string) $r[$dimension], 'value' => (int) $r[$metric]])->values()->all();
    }

    /**
     * @param  callable(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    private function guard(callable $build): array
    {
        if (! self::configured()) {
            return ['status' => 'not_configured'];
        }

        try {
            return ['status' => 'ok', ...$build()];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'error', 'message' => $this->errorMessage($e)];
        }
    }

    /** Google errors can be long JSON blobs: keep the human part only (never credentials — the API does not echo them). */
    private function errorMessage(Throwable $e): string
    {
        $message = $e->getMessage();
        $decoded = json_decode($message, true);
        if (is_array($decoded)) {
            $message = (string) ($decoded['message'] ?? $decoded['error']['message'] ?? $message);
        }

        return mb_strimwidth($message, 0, 240, '…');
    }

    private function date(mixed $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }

    private function host(string $referrer): string
    {
        // '' = direct visits / no referrer (the page shows a translated label).
        return $referrer === '' || $referrer === '(not set)' ? '' : (parse_url($referrer, PHP_URL_HOST) ?: $referrer);
    }
}
