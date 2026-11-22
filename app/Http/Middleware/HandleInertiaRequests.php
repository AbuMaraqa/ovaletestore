<?php

namespace App\Http\Middleware;

use App\Actions\Cart\ResolveCart;
use App\Models\Category;
use App\Settings\ContactSettings;
use App\Settings\GeneralSettings;
use App\Support\Translations;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * Shared props are a strict whitelist: nothing internal (no emails of others, no config, no IDs beyond the user's own).
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $user = $request->user();
        $area = $request->routeIs('admin.*') ? 'admin' : 'storefront';

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatarUrl(),
                    'is_staff' => $user->can('access-admin'),
                ] : null,
                // Permission names drive menu visibility only — every action is re-authorized server-side.
                'permissions' => fn () => $user && $request->routeIs('admin.*') ? $user->getAllPermissions()->pluck('name') : [],
            ],

            'locale' => [
                'current' => $locale,
                'dir' => LaravelLocalization::getCurrentLocaleDirection(),
                'available' => collect(LaravelLocalization::getSupportedLocales())->map(fn ($meta, $code) => [
                    'code' => $code,
                    'native' => $meta['native'],
                    'url' => LaravelLocalization::getLocalizedURL($code, null, [], false),
                ])->values(),
            ],

            'site' => fn () => [
                'name' => app(GeneralSettings::class)->siteName($locale),
                'tagline' => app(GeneralSettings::class)->tagline[$locale] ?? null,
                'logo' => ($logo = app(GeneralSettings::class)->logo_path) ? asset('storage/'.$logo) : null,
                'whatsapp' => app(ContactSettings::class)->whatsapp,
                'phone' => app(ContactSettings::class)->phone,
            ],

            'cart_count' => fn () => $request->routeIs('admin.*') ? 0
                : (app(ResolveCart::class)->handle($request, create: false)?->itemCount() ?? 0),

            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],

            // Storefront navigation (top-level categories), sent once per locale.
            'nav_categories' => $area === 'storefront'
                ? Inertia::once(fn () => Category::query()->active()->whereNull('parent_id')->ordered()
                    ->with('translations')
                    ->whereHas('products', fn ($q) => $q->published())
                    ->get()
                    ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'url' => route('storefront.category', ['slug' => $c->slug])]))
                    ->as("nav.{$locale}")
                : [],

            // UI strings for the current locale, sent once and cached client-side.
            'translations' => Inertia::once(fn () => Translations::forFrontend($locale, $area))->as("translations.{$area}.{$locale}"),
        ];
    }
}
