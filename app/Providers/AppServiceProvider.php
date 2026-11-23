<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\User;
use App\Observers\CatalogCacheObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
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

    private function configureRateLimiting(): void
    {
        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute(60)->by($request->session()->getId()));
        RateLimiter::for('checkout', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(30)->by($request->ip()),
        ]);
    }
}
