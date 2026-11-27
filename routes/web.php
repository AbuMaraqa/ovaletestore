<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\OrderDocumentController;
use App\Http\Controllers\Pos;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Storefront;
use App\Http\Middleware\ApplyStorefrontCurrency;
use App\Settings\GeneralSettings;
use App\Support\ActivityLog;
use App\Support\SiteIcons;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Spatie\Honeypot\ProtectAgainstSpam;

/*
| Non-localized technical routes.
*/
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/site.webmanifest', fn () => response()->json(
    SiteIcons::manifest(app(GeneralSettings::class)->siteName('ar')), 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
))->name('manifest');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemaps/{locale}-{type}.xml', [SitemapController::class, 'show'])
    ->whereIn('locale', ['ar', 'en', 'he'])->whereIn('type', ['pages', 'categories', 'products'])
    ->name('sitemap.show');

/*
| Localized application: Arabic at "/", English at "/en", Hebrew at "/he".
| Legacy WooCommerce URLs (/product/{slug}, /product-category/{slug}) are preserved as-is.
*/
Route::prefix(LaravelLocalization::setLocale())
    ->middleware(['localizationRedirect', 'localize'])
    ->group(function () {
        // Fortify (login, logout, password reset, 2FA) inside the locale prefix.
        require __DIR__.'/auth.php';

        Route::name('storefront.')->middleware(ApplyStorefrontCurrency::class)->group(function () {
            Route::get('/', Storefront\HomeController::class)->name('home');
            Route::get('/shop', [Storefront\CatalogController::class, 'index'])->name('shop');
            Route::get('/categories', [Storefront\CatalogController::class, 'categories'])->name('categories');
            Route::get('/product-category/{slug}', [Storefront\CatalogController::class, 'category'])->name('category');
            Route::get('/product/{slug}', [Storefront\ProductController::class, 'show'])->name('product');
            Route::get('/brands', [Storefront\CatalogController::class, 'brands'])->name('brands');
            Route::get('/brand/{slug}', [Storefront\CatalogController::class, 'brand'])->name('brand');
            Route::get('/search/suggest', [Storefront\SearchController::class, 'suggest'])->middleware('throttle:60,1')->name('search.suggest');
            Route::post('/currency', Storefront\CurrencyController::class)->middleware('throttle:30,1')->name('currency');
            Route::get('/contact', [Storefront\PageController::class, 'contact'])->name('contact');
            Route::get('/about', [Storefront\PageController::class, 'about'])->name('about');

            Route::get('/cart', [Storefront\CartController::class, 'show'])->name('cart');
            Route::middleware('throttle:cart')->group(function () {
                Route::post('/cart/items', [Storefront\CartController::class, 'store'])->name('cart.store');
                Route::patch('/cart/items/{item}', [Storefront\CartController::class, 'update'])->name('cart.update');
                Route::delete('/cart/items/{item}', [Storefront\CartController::class, 'destroy'])->name('cart.destroy');
            });

            Route::get('/checkout', [Storefront\CheckoutController::class, 'show'])->name('checkout');
            Route::post('/checkout', [Storefront\CheckoutController::class, 'store'])
                ->middleware(['throttle:checkout', ProtectAgainstSpam::class])
                ->name('checkout.store');
            Route::get('/order/{order}/thanks', [Storefront\CheckoutController::class, 'thanks'])
                ->middleware('signed')->name('order.thanks');
        });

        // Customer account (any signed-in user).
        Route::prefix('account')->name('account.')->middleware(['auth', ApplyStorefrontCurrency::class])->group(function () {
            Route::get('/', [Storefront\AccountController::class, 'dashboard'])->name('dashboard');
            Route::get('/orders', [Storefront\AccountController::class, 'orders'])->name('orders');
            Route::get('/orders/{number}', [Storefront\AccountController::class, 'order'])->name('orders.show');
            Route::get('/orders/{number}/invoice', [OrderDocumentController::class, 'customerInvoice'])->middleware('throttle:30,1')->name('orders.invoice');
            Route::put('/profile', [Storefront\AccountController::class, 'updateProfile'])->name('profile.update');
            Route::post('/trader-request', [Storefront\AccountController::class, 'requestTrader'])->middleware('throttle:5,1')->name('trader.request');
        });

        // iPad POS for sales reps: orders for companies, on account.
        Route::prefix('pos')->name('pos.')->middleware(['auth', 'can:access-pos'])->group(function () {
            Route::get('/', [Pos\PosController::class, 'index'])->name('index');
            Route::get('/companies/new', [Pos\PosCompanyController::class, 'create'])->name('companies.create');
            Route::post('/companies', [Pos\PosCompanyController::class, 'store'])->middleware('throttle:20,1')->name('companies.store');
            Route::get('/companies/{company}/edit', [Pos\PosCompanyController::class, 'edit'])->name('companies.edit');
            Route::put('/companies/{company}', [Pos\PosCompanyController::class, 'update'])->middleware('throttle:30,1')->name('companies.update');
            Route::get('/company/{company}', [Pos\PosController::class, 'create'])->name('create');
            Route::post('/orders', [Pos\PosController::class, 'store'])->middleware('throttle:30,1')->name('orders.store');
            Route::get('/orders', [Pos\PosController::class, 'orders'])->name('orders');
            Route::get('/orders/{order}', [Pos\PosController::class, 'show'])->name('orders.show');
            Route::get('/orders/{order}/pdf', [Pos\PosController::class, 'pdf'])->middleware('throttle:30,1')->name('orders.pdf');
        });

        Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:access-admin'])->group(function () {
            Route::get('/', Admin\DashboardController::class)->name('dashboard');
            Route::patch('notifications/{id}/read', [Admin\NotificationController::class, 'read'])->name('notifications.read');
            Route::post('notifications/read-all', [Admin\NotificationController::class, 'readAll'])->name('notifications.read-all');

            Route::resource('products', Admin\ProductController::class)->except('show');
            Route::post('products/{product}/media', [Admin\ProductMediaController::class, 'store'])->name('products.media.store');
            Route::patch('products/{product}/media/order', [Admin\ProductMediaController::class, 'reorder'])->name('products.media.reorder');
            Route::delete('products/{product}/media/{media}', [Admin\ProductMediaController::class, 'destroy'])->name('products.media.destroy');

            Route::resource('categories', Admin\CategoryController::class)->except('show');
            Route::resource('attributes', Admin\AttributeController::class)->except('show');
            Route::resource('brands', Admin\BrandController::class)->except('show');
            Route::resource('shipping-methods', Admin\ShippingMethodController::class)->except('show');
            Route::get('companies/{company}/statement', [Admin\CompanyController::class, 'statementPdf'])->name('companies.statement');
            Route::resource('companies', Admin\CompanyController::class);
            Route::post('companies/{company}/payments', [Admin\CompanyPaymentController::class, 'store'])->name('companies.payments.store');
            Route::delete('companies/{company}/payments/{payment}', [Admin\CompanyPaymentController::class, 'destroy'])->name('companies.payments.destroy');

            Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/labels', [OrderDocumentController::class, 'shippingLabels'])->middleware('throttle:20,1')->name('orders.labels');
            Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
            Route::get('orders/{order}/invoice', [OrderDocumentController::class, 'invoice'])->name('orders.invoice');
            Route::get('orders/{order}/shipping-label', [OrderDocumentController::class, 'shippingLabel'])->name('orders.label');
            Route::get('orders/{order}/sales-order', [OrderDocumentController::class, 'salesOrder'])->name('orders.sales-order');
            Route::delete('orders/{order}', [Admin\OrderController::class, 'destroy'])->name('orders.destroy');
            Route::patch('orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');

            Route::resource('users', Admin\UserController::class)->except('show');
            Route::resource('roles', Admin\RoleController::class)->except('show');
            Route::resource('customers', Admin\CustomerController::class);
            Route::get('traders', [Admin\TraderController::class, 'index'])->name('traders.index');
            Route::patch('traders/{trader}/review', [Admin\TraderController::class, 'review'])->name('traders.review');
            Route::resource('price-tiers', Admin\PriceTierController::class)->except('show');
            Route::resource('currencies', Admin\CurrencyController::class)->except('show');
            Route::patch('currencies/{currency}/default', [Admin\CurrencyController::class, 'makeDefault'])->name('currencies.default');

            // Shared table endpoints (App\Tables): CSV export and bulk actions for every list.
            Route::get('tables/{table}/export', Admin\TableExportController::class)->middleware('throttle:20,1')->name('tables.export');
            Route::post('tables/{table}/bulk', Admin\TableBulkController::class)->middleware('throttle:30,1')->name('tables.bulk');

            Route::get('activity', [Admin\ActivityController::class, 'index'])->name('activity.index');
            Route::get('activity/{type}/{id}', [Admin\ActivityController::class, 'record'])
                ->whereIn('type', array_keys(ActivityLog::SUBJECTS))->whereNumber('id')->name('activity.record');

            Route::post('settings/mail/test', [Admin\SettingsController::class, 'testMail'])->middleware('throttle:5,1')->name('settings.mail.test');
            Route::get('settings/{group}', [Admin\SettingsController::class, 'edit'])
                ->whereIn('group', Admin\SettingsController::GROUPS)->name('settings.edit');
            Route::put('settings/{group}', [Admin\SettingsController::class, 'update'])
                ->whereIn('group', Admin\SettingsController::GROUPS)->name('settings.update');
        });
    });
