<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\SeoSettings;
use App\Support\Analytics\SiteAnalytics;
use App\Support\Analytics\StoreVisits;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Site traffic from two sources, side by side (?source=internal|google):
 *  - internal: the store's own counter (StoreVisits, shetabit/visitor) — always available, the default;
 *  - google: Google Analytics 4 (SiteAnalytics, spatie/laravel-analytics) once its Data API is connected.
 * Reports are deferred: the page shell renders at once.
 */
class AnalyticsController extends Controller
{
    public const SOURCES = ['internal', 'google'];

    public function index(Request $request, SeoSettings $seo): Response
    {
        $this->authorize('analytics.view');
        $days = in_array($request->integer('days'), SiteAnalytics::PERIODS, true) ? $request->integer('days') : 28;
        $source = in_array($request->query('source'), self::SOURCES, true) ? (string) $request->query('source') : 'internal';
        $reports = $source === 'google' ? app(SiteAnalytics::class) : app(StoreVisits::class);

        return Inertia::render('Admin/Analytics/Index', [
            'days' => $days,
            'periods' => SiteAnalytics::PERIODS,
            'source' => $source,
            'google_connected' => SiteAnalytics::configured(),
            'tracking' => [
                'measurement_id' => $seo->ga_measurement_id,
                'enabled' => (bool) config('analytics.tracking'),
            ],
            'can_edit_settings' => $request->user()?->can('settings.seo') ?? false,
            'report' => Inertia::defer(fn () => $reports->report($days)),
            'realtime' => Inertia::defer(fn () => $reports->realtime(), 'realtime'),
        ]);
    }
}
