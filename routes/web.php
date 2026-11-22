<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Storefront;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Spatie\Honeypot\ProtectAgainstSpam;

/*
| Non-localized technical routes.
*/
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
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

        Route::name('storefront.')->group(function () {
            Route::get('/', Storefront\HomeController::class)->name('home');
            Route::get('/shop', [Storefront\CatalogController::class, 'index'])->name('shop');
            Route::get('/product-category/{slug}', [Storefront\CatalogController::class, 'category'])->name('category');
            Route::get('/product/{slug}', [Storefront\ProductController::class, 'show'])->name('product');

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

        Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:access-admin'])->group(function () {
            Route::get('/', Admin\DashboardController::class)->name('dashboard');

            Route::resource('products', Admin\ProductController::class)->except('show');
            Route::post('products/{product}/media', [Admin\ProductMediaController::class, 'store'])->name('products.media.store');
            Route::patch('products/{product}/media/order', [Admin\ProductMediaController::class, 'reorder'])->name('products.media.reorder');
            Route::delete('products/{product}/media/{media}', [Admin\ProductMediaController::class, 'destroy'])->name('products.media.destroy');

            Route::resource('categories', Admin\CategoryController::class)->except('show');
            Route::resource('attributes', Admin\AttributeController::class)->except('show');
            Route::resource('brands', Admin\BrandController::class)->except('show');

            Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
            Route::patch('orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');

            Route::resource('users', Admin\UserController::class)->except('show');

            Route::get('settings/{group}', [Admin\SettingsController::class, 'edit'])
                ->whereIn('group', Admin\SettingsController::GROUPS)->name('settings.edit');
            Route::put('settings/{group}', [Admin\SettingsController::class, 'update'])
                ->whereIn('group', Admin\SettingsController::GROUPS)->name('settings.update');
        });
    });
