<?php

use App\Models\Brand;
use App\Models\Seo;
use App\Settings\GeneralSettings;
use App\Settings\SeoSettings;
use App\Support\Seo\SitemapBuilder;

/** ralphjsmit/laravel-seo (head tags via Inertia serverHead) + spatie/laravel-sitemap. */
beforeEach(fn () => SitemapBuilder::flush());

it('builds the product head with laravel-seo: title, canonical, hreflang, Open Graph and JSON-LD', function () {
    $product = makeSimpleProduct('عسل طبيعي');

    $this->get(route('storefront.product', ['slug' => $product->slug]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('seo', function ($tags) use ($product) {
            $site = app(GeneralSettings::class)->siteName();
            expect(seoTag($tags, 'title'))->toContain("عسل طبيعي | {$site}")
                ->and(seoTag($tags, 'canonical'))->toContain(rawurlencode($product->slug))
                ->and(seoTag($tags, 'alternate-en'))->toContain('/en/product/')
                ->and(seoTag($tags, 'alternate-x-default'))->toContain('/ar/product/')
                ->and(seoTag($tags, 'og:type'))->toContain('content="product"')
                ->and(seoTag($tags, 'og:locale'))->toContain('ar_AR')
                ->and(seoTag($tags, 'sitemap'))->toContain('/sitemap.xml')
                ->and(seoTag($tags, 'meta-robots'))->toContain('index,follow')
                ->and(seoTag($tags, 'ld-json'))->toContain('"@type":"Product"')
                ->and(seoTag($tags, 'ld-json:2'))->toContain('"@type":"BreadcrumbList"');

            return true;
        }));
});

it('renders the same tags server-side for crawlers (no JavaScript needed)', function () {
    $product = makeSimpleProduct('زيت زيتون');

    $html = $this->get(route('storefront.product', ['slug' => $product->slug]))->getContent();
    $head = substr($html, 0, strpos($html, '</head>'));

    expect($head)->toContain('<title data-inertia="title">زيت زيتون')
        ->toContain('rel="canonical"')
        ->toContain('hreflang="he"')
        ->toContain('application/ld+json');
});

it('applies the per-record robots override and leaves that record out of the sitemap', function () {
    $hidden = makeSimpleProduct('منتج مخفي');
    $shown = makeSimpleProduct('منتج ظاهر');
    $hidden->seo()->firstOrNew()->fill(['robots' => 'noindex,follow'])->save();

    $this->get(route('storefront.product', ['slug' => $hidden->fresh()->slug]))
        ->assertInertia(fn ($page) => $page->where('seo', fn ($tags) => str_contains(seoTag($tags, 'meta-robots'), 'noindex,follow')));

    $xml = $this->get(route('sitemap.show', ['locale' => 'ar', 'type' => 'products']))->assertOk()->getContent();
    expect($xml)->toContain(rawurlencode($shown->slug))->not->toContain(rawurlencode($hidden->fresh()->slug));
});

it('turns every page into noindex,nofollow when indexing is switched off', function () {
    $settings = app(SeoSettings::class);
    $settings->allow_indexing = false;
    $settings->save();

    $this->get(route('storefront.home'))
        ->assertInertia(fn ($page) => $page->where('seo', fn ($tags) => str_contains(seoTag($tags, 'meta-robots'), 'noindex,nofollow')));
    expect($this->get(route('sitemap.index'))->getContent())->not->toContain('<sitemap>');
});

it('prints the webmaster verification codes and the X account from the settings', function () {
    $settings = app(SeoSettings::class);
    $settings->google_verification = 'abc123-XYZ';
    $settings->twitter_username = 'ovalete';
    $settings->save();

    $this->get(route('storefront.home'))
        ->assertInertia(fn ($page) => $page->where('seo', fn ($tags) => str_contains(seoTag($tags, 'meta-google-site-verification'), 'abc123-XYZ')
            && str_contains(seoTag($tags, 'meta-twitter:site'), '@ovalete')));
});

it('creates and removes the laravel-seo row with the record', function () {
    $product = makeSimpleProduct();
    expect(Seo::query()->where('model_type', $product->getMorphClass())->where('model_id', $product->id)->exists())->toBeTrue();

    $product->forceDelete();
    expect(Seo::query()->where('model_id', $product->id)->where('model_type', $product->getMorphClass())->exists())->toBeFalse();
});

it('serves the sitemap index with lastmod and one sitemap per language and type (brands included)', function () {
    $product = makeSimpleProduct();
    $brand = Brand::query()->create(['is_active' => true, 'ar' => ['name' => 'لوتس', 'slug' => 'لوتس'], 'en' => ['name' => 'Lotus', 'slug' => 'lotus']]);
    $product->update(['brand_id' => $brand->id]);

    $index = $this->get(route('sitemap.index'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
    expect($index->getContent())->toContain('/sitemaps/he-brands.xml')->toContain('<lastmod>');

    $brands = $this->get(route('sitemap.show', ['locale' => 'en', 'type' => 'brands']))->assertOk()->getContent();
    expect($brands)->toContain('/en/brand/lotus')->toContain('hreflang="ar"')->toContain('hreflang="x-default"');
});

it('rebuilds every sitemap with sitemap:generate', function () {
    makeSimpleProduct();

    $this->artisan('sitemap:generate')->assertSuccessful();
    expect(cache()->has('sitemap:ar:products'))->toBeTrue()->and(cache()->has('sitemap:index'))->toBeTrue();
});

it('marks up the home page for Google\'s site name: WebSite with the store name and its other-language names', function () {
    $general = app(GeneralSettings::class);
    $general->site_name = ['ar' => 'أوفاليتي ستور', 'en' => 'Ovalete Store', 'he' => 'Ovalete Store'];
    $general->save();

    $this->get(route('storefront.home'))
        ->assertInertia(fn ($page) => $page->where('seo', function ($tags) {
            $website = collect($tags)
                ->map(fn (string $tag) => json_decode(strip_tags($tag), true))
                ->first(fn ($data) => is_array($data) && ($data['@type'] ?? null) === 'WebSite');

            return $website['name'] === 'أوفاليتي ستور' && $website['alternateName'] === ['Ovalete Store'];
        }));
});
