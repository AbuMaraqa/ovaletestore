<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\SeoSettings;
use App\Support\Analytics\SiteAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Site traffic from Google Analytics 4 (spatie/laravel-analytics). Reports are deferred: the page shell renders at once. */
class AnalyticsController extends Controller
{
    public function index(Request $request, SiteAnalytics $analytics, SeoSettings $seo): Response
    {
        $this->authorize('analytics.view');
        $days = in_array($request->integer('days'), SiteAnalytics::PERIODS, true) ? $request->integer('days') : 28;

        return Inertia::render('Admin/Analytics/Index', [
            'days' => $days,
            'periods' => SiteAnalytics::PERIODS,
            'configured' => SiteAnalytics::configured(),
            // Where the server expects the key (relative to the project; the file itself never leaves the server).
            'credentials_path' => is_string($path = config('analytics.service_account_credentials_json'))
                ? Str::after(str_replace('\\', '/', $path), str_replace('\\', '/', base_path()).'/') : '',
            'tracking' => [
                'measurement_id' => $seo->ga_measurement_id,
                'enabled' => (bool) config('analytics.tracking'),
            ],
            'can_edit_settings' => $request->user()?->can('settings.seo') ?? false,
            'report' => Inertia::defer(fn () => $analytics->report($days)),
            'realtime' => Inertia::defer(fn () => $analytics->realtime(), 'realtime'),
        ]);
    }
}
