<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\RouteCollection;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Mcamara\LaravelLocalization\LaravelLocalization as Localization;

abstract class TestCase extends BaseTestCase
{
    /**
     * mcamara/laravel-localization registers the locale prefix from the *boot* request URL.
     * Tests boot once at "/", so to exercise /en or /he routes we re-register the routes
     * with the package's documented ROUTING_LOCALE override (same mechanism as route:trans:cache).
     */
    protected function useRouteLocale(string $locale): static
    {
        putenv(Localization::ENV_ROUTE_KEY.'='.$locale);

        $router = $this->app['router'];
        $router->setRoutes(new RouteCollection);
        $router->middleware('web')->group(base_path('routes/web.php'));
        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();

        putenv(Localization::ENV_ROUTE_KEY);
        app()->setLocale($locale);
        LaravelLocalization::setLocale($locale);
        url()->setRoutes($router->getRoutes());

        return $this;
    }
}
