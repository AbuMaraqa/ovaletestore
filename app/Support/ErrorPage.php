<?php

namespace App\Support;

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Seo\SeoData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Symfony\Component\HttpFoundation\Response;

/**
 * The branded error page (Pages/Storefront/Error.vue) inside the store layout.
 *
 * A URL that matches no route never runs the web middleware, so the page would render without the
 * shared props the layout needs (site name, logo, menu, translations) — a blank or unstyled page.
 * Here we give it the same session, locale and shared props a normal storefront page gets.
 */
final class ErrorPage
{
    public static function render(Request $request, int $status): Response
    {
        if (! $request->hasSession()) {
            $request->setLaravelSession(app('session')->driver());
        }

        $locale = (string) $request->segment(1);
        if (array_key_exists($locale, LaravelLocalization::getSupportedLocales())) {
            LaravelLocalization::setLocale($locale);
        }

        $middleware = app(HandleInertiaRequests::class);
        Inertia::share($middleware->share($request));
        Inertia::version(fn () => $middleware->version($request));

        $title = $status === 404 ? __('storefront.not_found_title') : __("http-statuses.{$status}");

        return Inertia::render('Storefront/Error', [
            'status' => $status,
            'seo' => SeoData::make($title)->noindex(),
        ])->toResponse($request)->setStatusCode($status);
    }
}
