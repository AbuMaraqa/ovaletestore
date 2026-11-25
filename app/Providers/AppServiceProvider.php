<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\User;
use App\Observers\CatalogCacheObserver;
use App\Support\CurrencyContext;
use App\Support\PriceContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Mcamara\LaravelLocalization\Traits\LoadsTranslatedCachedRoutes;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class AppServiceProvider extends ServiceProvider
{
    use LoadsTranslatedCachedRoutes;

    public function register(): void
    {
        // One pricing context per request / queue job (trader tier of the signed-in user).
        $this->app->scoped(PriceContext::class);
        $this->app->scoped(CurrencyContext::class);
    }

    public function boot(): void
    {
        // `route:trans:cache` writes one route file per locale; without this the framework loads the
        // locale-less default file and every /ar, /en, /he URL 404s once routes are cached.
        RouteServiceProvider::loadCachedRoutesUsing(fn () => $this->loadCachedRoutes());

        // Fail loudly outside production on silently discarded / missing attributes,
        // and on lazy loading (N+1) during local HTTP requests.
        $strict = ! $this->app->isProduction();
        Model::preventSilentlyDiscardingAttributes($strict);
        Model::preventAccessingMissingAttributes($strict);
        Model::preventLazyLoading($strict && ! $this->app->runningInConsole());

        JsonResource::withoutWrapping();

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8));

        Vite::prefetch(concurrency: 3);
        if (config('csp.enabled') && config('csp.nonce_enabled')) {
            Vite::useCspNonce(app('csp-nonce'));
        }

        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureActivityLog();

        // ProductVariant too: prices and stock shown in the POS catalog.
        foreach ([Product::class, ProductVariant::class, Category::class, ProductTranslation::class, CategoryTranslation::class] as $model) {
            $model::observe(CatalogCacheObserver::class);
        }
    }

    private function configureAuthorization(): void
    {
        Gate::before(fn (User $user) => $user->hasRole('super-admin') ? true : null);

        Gate::define('access-admin', fn (User $user) => $user->is_active && $user->can('admin.access'));
        Gate::define('access-pos', fn (User $user) => $user->is_active && $user->can('pos.access'));
    }

    /** Every audit entry records where it came from (IP + browser) when it happens during a web request. */
    private function configureActivityLog(): void
    {
        LogActivityAction::beforeLogging(function (ActivityContract $activity): void {
            if (! $activity instanceof Model || ($this->app->runningInConsole() && ! $this->app->runningUnitTests())) {
                return;
            }
            $request = request();
            $activity->setAttribute('properties', collect($activity->getAttribute('properties'))->merge([
                'ip' => $request->ip(),
                'agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]));
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute(60)->by($request->session()->getId()));
        RateLimiter::for('checkout', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(30)->by($request->ip()),
        ]);
    }
}
