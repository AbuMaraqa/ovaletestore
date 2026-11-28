<?php

namespace App\Support\Analytics;

use App\Models\BrandTranslation;
use App\Models\CategoryTranslation;
use App\Models\ProductTranslation;
use App\Models\StoreVisit;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Site traffic from the store's own counter (shetabit/visitor rows, see TrackStoreVisit) in the same shape as the
 * Google Analytics report (SiteAnalytics), so Admin → Site traffic and the dashboard card work with no Google setup.
 *
 * Visitors are distinct daily hashes (a person coming back on another day counts again, as in other cookie-less
 * counters); sessions are distinct session hashes; channels and referrers count entrances. No countries (no IP is
 * kept) and no new/returning split. Reports are cached 5 minutes, "right now" 1 minute.
 */
class StoreVisits
{
    private const CACHE_SECONDS = 300;

    /** @return array<string, mixed> */
    public function report(int $days): array
    {
        $days = in_array($days, SiteAnalytics::PERIODS, true) ? $days : 28;

        return Cache::remember("store-visits:report:{$days}:".app()->getLocale(), self::CACHE_SECONDS, function () use ($days): array {
            $from = Carbon::today()->subDays($days);

            return [
                'status' => 'ok',
                'source' => 'internal',
                'kpis' => $this->kpis($from),
                'trend' => $this->trend($days),
                'pages' => $this->pages($from)->map(fn (array $p) => ['title' => $p['title'], 'url' => $p['url'], 'views' => $p['views']])->values(),
                'page_visitors' => $this->pages($from)->sortByDesc('visitors')->map(fn (array $p) => [
                    'title' => $p['title'], 'visitors' => $p['visitors'], 'views' => $p['views'],
                ])->values(),
                'recent' => $this->recent(Carbon::today()->subDays(min($days, 7))),
                'referrers' => $this->count($from, 'referrer_host', 8, entrancesOnly: true)
                    ->map(fn (array $r) => ['label' => $r['label'], 'views' => $r['value']])->values(),
                'browsers' => $this->count($from, 'browser', 6)->map(fn (array $r) => ['label' => $r['label'], 'views' => $r['value']])->values(),
                'systems' => $this->count($from, 'platform', 6)->map(fn (array $r) => ['label' => $r['label'], 'views' => $r['value']])->values(),
                'channels' => $this->count($from, 'channel', 8, entrancesOnly: true)->values(),
                'devices' => $this->count($from, 'device_type', 4)->values(),
            ];
        });
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return Cache::remember('store-visits:summary:'.app()->getLocale(), self::CACHE_SECONDS, fn () => [
            'status' => 'ok',
            'source' => 'internal',
            'kpis' => $this->kpis(Carbon::today()->subDays(7)),
            'trend' => $this->trend(7),
            'realtime' => $this->realtime(),
        ]);
    }

    /** Distinct sessions with a page view in the last 30 minutes (the window GA4 uses for "right now"). */
    public function realtime(): int
    {
        return Cache::remember('store-visits:realtime', 60, fn () => (int) StoreVisit::query()
            ->where('created_at', '>=', now()->subMinutes(30))
            ->distinct()->count('session_hash'));
    }

    /** @return array{visitors: int, views: int, sessions: int, engagement_rate: float, avg_duration: int, bounce_rate: float} */
    private function kpis(Carbon $from): array
    {
        $base = fn () => StoreVisit::query()->where('created_at', '>=', $from);
        $sessions = $base()->whereNotNull('session_hash')->selectRaw('session_hash, COUNT(*) AS views, '.$this->durationSql().' AS duration')
            ->groupBy('session_hash');
        $totals = DB::query()->fromSub($sessions, 's')
            ->selectRaw('COUNT(*) AS sessions, SUM(CASE WHEN views = 1 THEN 1 ELSE 0 END) AS bounces, AVG(duration) AS duration')
            ->first();

        $count = (int) ($totals->sessions ?? 0);
        $bounce = $count ? round((int) $totals->bounces / $count * 100, 1) : 0.0;

        return [
            'visitors' => (int) $base()->distinct()->count('visitor_hash'),
            'views' => (int) $base()->count(),
            'sessions' => $count,
            'engagement_rate' => $count ? round(100 - $bounce, 1) : 0.0,
            'avg_duration' => (int) round((float) ($totals->duration ?? 0)),
            'bounce_rate' => $bounce,
        ];
    }

    /** One point per day, days without visits as 0. @return list<array{date: string, label: string, visitors: int, views: int}> */
    private function trend(int $days): array
    {
        $rows = $this->visits()->where('created_at', '>=', Carbon::today()->subDays($days))
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors')
            ->groupByRaw('DATE(created_at)')->get()->keyBy('day');

        $points = [];
        foreach (range($days, 0) as $ago) {
            $day = Carbon::today()->subDays($ago);
            $row = $rows->get($day->toDateString());
            $points[] = [
                'date' => $day->toDateString(),
                'label' => $day->translatedFormat('j M'),
                'visitors' => (int) ($row->visitors ?? 0),
                'views' => (int) ($row->views ?? 0),
            ];
        }

        return $points;
    }

    /** Top 10 pages with readable titles. @return Collection<int, array{title: string, url: string, views: int, visitors: int}> */
    private function pages(Carbon $from): Collection
    {
        $rows = $this->visits()->where('created_at', '>=', $from)->whereNotNull('path')
            ->selectRaw('path, route_name, subject, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors')
            ->groupBy('path', 'route_name', 'subject')->orderByDesc('views')->limit(10)->get();

        return $rows->map(fn ($r) => [
            'title' => $this->title((string) $r->route_name, $r->subject, (string) $r->path),
            'url' => request()->getHost().$r->path,
            'views' => (int) $r->views,
            'visitors' => (int) $r->visitors,
        ]);
    }

    /** @return list<array{date: string, title: string, visitors: int, views: int}> */
    private function recent(Carbon $from): array
    {
        return $this->visits()->where('created_at', '>=', $from)->whereNotNull('path')
            ->selectRaw('DATE(created_at) AS day, path, route_name, subject, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors')
            ->groupByRaw('DATE(created_at), path, route_name, subject')
            ->orderByDesc('day')->orderByDesc('views')->limit(12)->get()
            ->map(fn ($r) => [
                'date' => (string) $r->day,
                'title' => $this->title((string) $r->route_name, $r->subject, (string) $r->path),
                'visitors' => (int) $r->visitors,
                'views' => (int) $r->views,
            ])->all();
    }

    /** @return Collection<int, array{label: string, value: int}> */
    private function count(Carbon $from, string $column, int $limit, bool $entrancesOnly = false): Collection
    {
        return $this->visits()->where('created_at', '>=', $from)
            ->when($entrancesOnly, fn (QueryBuilder $q) => $q->whereNotNull('channel'))
            ->when($column !== 'referrer_host', fn (QueryBuilder $q) => $q->whereNotNull($column)->where($column, '!=', ''))
            ->selectRaw("{$column} AS label, COUNT(*) AS value")
            ->groupBy($column)->orderByDesc('value')->limit($limit)->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'value' => (int) $r->value]);
    }

    /** Product / category / brand name from the slug, a page name from the route, else the path. */
    private function title(string $route, ?string $slug, string $path): string
    {
        $model = match ($route) {
            'storefront.product' => ProductTranslation::class,
            'storefront.category' => CategoryTranslation::class,
            'storefront.brand' => BrandTranslation::class,
            default => null,
        };
        if ($model && $slug) {
            $name = $model::query()->where('slug', $slug)->value('name');
            if ($name) {
                return (string) $name;
            }
        }

        $key = match ($route) {
            'storefront.home' => 'common.home',
            'storefront.shop' => 'storefront.products',
            'storefront.categories' => 'storefront.categories_nav',
            'storefront.brands' => 'storefront.brands',
            'storefront.about' => 'storefront.our_story',
            'storefront.contact' => 'storefront.contact_us',
            'storefront.cart' => 'cart.title',
            'storefront.checkout' => 'cart.checkout_title',
            default => null,
        };

        return $key && __($key) !== $key ? __($key) : $path;
    }

    /** The visits table as a plain query: report rows are aggregates, not models. */
    private function visits(): QueryBuilder
    {
        return StoreVisit::query()->toBase();
    }

    /** Seconds between a session's first and last page view (portable: SQLite in development/tests, MySQL live). */
    private function durationSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(strftime('%s', MAX(created_at)) - strftime('%s', MIN(created_at)))"
            : 'TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at))';
    }
}
