<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every page URL carries its language (/ar, /en, /he). A GET/HEAD request without a locale prefix
 * (e.g. "/" or a legacy WooCommerce "/product/{slug}/") gets ONE permanent redirect to the
 * default-locale URL, so search engines transfer the old URLs' value and no duplicate pages exist.
 */
class RedirectToLocalePrefix
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        $first = explode('/', $path)[0];

        if (array_key_exists($first, LaravelLocalization::getSupportedLocales()) || $this->isIgnored($request)) {
            return $next($request);
        }

        $target = '/'.LaravelLocalization::getDefaultLocale().($path === '' ? '' : '/'.$path);
        $query = $request->getQueryString();

        return redirect()->to(url($target).($query ? '?'.$query : ''), 301);
    }

    private function isIgnored(Request $request): bool
    {
        foreach ((array) config('laravellocalization.urlsIgnored', []) as $pattern) {
            if ($request->is(ltrim($pattern, '/'))) {
                return true;
            }
        }

        return false;
    }
}
