<?php

namespace App\Http\Middleware;

use App\Actions\Cart\ResolveCart;
use App\Enums\OrderStatus;
use App\Enums\TraderStatus;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Order;
use App\Models\User;
use App\Settings\ContactSettings;
use App\Settings\GeneralSettings;
use App\Support\CurrencyContext;
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

    /**
     * The admin and the storefront ship different CSS bundles and Ziggy route lists (app.blade.php),
     * so crossing between them (e.g. login → admin) must be a full page load: a per-area asset
     * version makes Inertia answer 409 + location instead of swapping the page in place.
     */
    public function version(Request $request): ?string
    {
        $area = $request->routeIs('admin.*', 'two-factor.*', 'password.confirm*') ? 'admin' : 'storefront';

        return parent::version($request).'|'.$area;
    }

    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $user = $request->user();
        $area = match (true) {
            $request->routeIs('admin.*') => 'admin',
            $request->routeIs('pos.*') => 'pos',
            default => 'storefront',
        };

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar(),
                    'is_staff' => $isStaff = $user->can('access-admin'),
                    // Admin routes are excluded from the storefront Ziggy list, so the URL is sent to staff only.
                    'admin_url' => $isStaff ? route('admin.dashboard') : null,
                    'pos_url' => $user->can('access-pos') ? route('pos.index') : null,
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
                'social' => array_filter(app(ContactSettings::class)->social_links),
                'phone_lines' => app(ContactSettings::class)->phoneLinesFor($locale),
                'locations' => app(ContactSettings::class)->locationsFor($locale),
                // Storefront: the visitor's currency; admin/POS: the base currency (ApplyStorefrontCurrency).
                'currency' => app(CurrencyContext::class)->current()->code,
            ],

            'cart_count' => fn () => $request->routeIs('admin.*') ? 0
                : (app(ResolveCart::class)->handle($request, create: false)?->itemCount() ?? 0),

            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],

            // Admin header badges (only for staff who may see orders).
            'admin_badges' => fn () => $area === 'admin' && $user ? array_filter([
                'pending_orders' => $user->can('orders.view') ? Order::query()->where('status', OrderStatus::Pending)->count() : null,
                'pending_traders' => $user->can('traders.view') ? User::query()->where('trader_status', TraderStatus::Pending)->count() : null,
            ], fn ($count) => $count !== null) ?: null : null,

            // Staff notifications (new orders) for the header bell; polled by the admin layout.
            'admin_notifications' => fn () => $area === 'admin' && $user ? [
                'unread' => $user->unreadNotifications()->count(),
                'items' => $user->notifications()->latest()->limit(8)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'type' => $n->data['type'] ?? 'order.placed',
                    'number' => $n->data['number'] ?? null,
                    'customer' => $n->data['customer'] ?? null,
                    'total' => $n->data['total'] ?? null,
                    'read' => $n->read_at !== null,
                    'time' => $n->created_at->diffForHumans(),
                ]),
            ] : null,

            // Storefront navigation (top-level categories), sent once per locale.
            // Currency switcher (storefront only; hidden when the store sells in a single currency).
            'currencies' => fn () => $area === 'storefront' && ($list = app(CurrencyContext::class)->active())->count() > 1
                ? $list->map(fn (Currency $c) => ['code' => $c->code, 'label' => $c->label($locale), 'symbol' => $c->symbol($locale)])->values()->all()
                : null,

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
