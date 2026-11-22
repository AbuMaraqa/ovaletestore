<?php

use App\Models\Product;

it('serves Arabic at the root and English/Hebrew under prefixes with correct lang and dir', function (string $url, string $lang, string $dir) {
    makeSimpleProduct();

    ($lang === 'ar' ? $this : $this->useRouteLocale($lang))->get($url)->assertOk()->assertSee("lang=\"{$lang}\" dir=\"{$dir}\"", false);
})->with([
    'arabic' => ['/', 'ar', 'rtl'],
    'english' => ['/en', 'en', 'ltr'],
    'hebrew' => ['/he', 'he', 'rtl'],
]);

it('keeps the legacy WooCommerce Arabic product URL working', function () {
    makeSimpleProduct(overrides: ['translations' => ['ar' => ['name' => 'عجينة سكر', 'slug' => 'عجينة-سكّر-احمر']]]);

    $this->get('/product/'.rawurlencode('عجينة-سكّر-احمر'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Storefront/Products/Show'));
});

it('falls back he → en → ar for missing translations', function () {
    $product = makeSimpleProduct(name: 'زيت', overrides: ['translations' => ['en' => ['name' => 'Oil']]]);
    $product = Product::query()->with('translations')->find($product->id);

    app()->setLocale('he');
    expect($product->name)->toBe('Oil');

    $product->deleteTranslations('en');
    $product = Product::query()->with('translations')->find($product->id);
    expect($product->name)->toBe('زيت');
});

it('301-redirects a product slug from another locale to the canonical slug of the current locale', function () {
    $product = makeSimpleProduct(name: 'كاكاو', overrides: ['translations' => ['en' => ['name' => 'Cocoa Powder']]]);
    $arSlug = $product->translate('ar')->slug;

    $this->useRouteLocale('en')->get('/en/product/'.rawurlencode($arSlug))->assertRedirect('/en/product/cocoa-powder')->assertStatus(301);
});

it('renders hreflang alternates and canonical server-side', function () {
    $product = makeSimpleProduct(overrides: ['translations' => ['en' => ['name' => 'Vanilla']]]);

    $this->useRouteLocale('en')->get('/en/product/vanilla')
        ->assertOk()
        ->assertSee('rel="canonical" href="'.url('/en/product/vanilla').'"', false)
        ->assertSee('hreflang="he"', false)
        ->assertSee('hreflang="x-default"', false)
        ->assertSee('application/ld+json', false);
});

it('excludes private pages from the sitemap and lists localized product URLs', function () {
    makeSimpleProduct(overrides: ['translations' => ['en' => ['name' => 'Sitemap Item']]]);

    $xml = $this->get('/sitemaps/en-products.xml')->assertOk()->getContent();

    expect($xml)->toContain('/en/product/sitemap-item')
        ->toContain('hreflang="ar"')
        ->not->toContain('/cart')
        ->not->toContain('/admin');
});
