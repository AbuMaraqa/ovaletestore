<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleLegacyRedirects;
use App\Http\Middleware\RedirectToLocalePrefix;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ErrorPage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes;
use Spatie\Csp\AddCspHeaders;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Runs before routing: unprefixed page URLs → 301 to /ar/...
        $middleware->prepend(RedirectToLocalePrefix::class);

        $middleware->web(
            prepend: [HandleLegacyRedirects::class],
            append: [
                EnsureUserIsActive::class,
                HandleInertiaRequests::class,
                AddCspHeaders::class,
                SecurityHeaders::class,
            ],
        );

        $middleware->alias([
            'localize' => LaravelLocalizationRoutes::class,
            'localizationRedirect' => LaravelLocalizationRedirectFilter::class,
        ]);

        $middleware->encryptCookies(except: []);
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', (string) env('TRUSTED_PROXIES')) : null);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->can('access-admin') ? route('admin.dashboard') : route('account.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Branded, localized error pages in the store layout. 403/404 everywhere (they are not bugs);
        // 500/503 only in production, so local development keeps Laravel's debug page.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            $branded = in_array($status, [403, 404], true)
                || (! app()->environment(['local', 'testing']) && in_array($status, [500, 503], true));
            if ($branded && ! $request->expectsJson()) {
                return ErrorPage::render($request, $status);
            }

            if ($response->getStatusCode() === 419) {
                return back()->with('error', __('http-statuses.419'));
            }

            return $response;
        });
    })->create();
