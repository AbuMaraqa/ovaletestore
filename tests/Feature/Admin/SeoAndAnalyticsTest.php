<?php

use App\Settings\SeoSettings;
use App\Support\Analytics\SiteAnalytics;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Analytics\Facades\Analytics;

/** Admin side of laravel-seo (per-record overrides, settings) and spatie/laravel-analytics (reports, tracking). */
it('saves a product\'s search-engine overrides, with the product permission', function () {
    Storage::fake('public');
    $product = makeSimpleProduct();
    $url = route('admin.seo.update', ['type' => 'products', 'id' => $product->id]);

    $this->actingAs(staffUser('content-manager'))->put($url, [
        'robots' => 'noindex,follow',
        'canonical_url' => 'https://example.com/original',
        'image' => UploadedFile::fake()->image('share.jpg', 1200, 630),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $seo = $product->fresh()->seo;
    expect($seo->robots)->toBe('noindex,follow')
        ->and($seo->canonical_url)->toBe('https://example.com/original');
    Storage::disk('public')->assertExists($seo->image);

    // The share image wins over the gallery and the canonical points elsewhere.
    $this->get(route('storefront.product', ['slug' => $product->slug]))
        ->assertInertia(fn ($page) => $page->where('seo', fn ($tags) => str_contains(seoTag($tags, 'og:image'), 'seo/')
            && str_contains(seoTag($tags, 'canonical'), 'https://example.com/original')));

    $this->actingAs(staffUser('order-manager'))->put($url, ['robots' => null])->assertForbidden();
});

it('validates the overrides', function () {
    $product = makeSimpleProduct();

    $this->actingAs(staffUser('admin'))
        ->put(route('admin.seo.update', ['type' => 'products', 'id' => $product->id]), ['robots' => 'index,nofollow,evil', 'canonical_url' => 'javascript:alert(1)'])
        ->assertSessionHasErrors(['robots', 'canonical_url']);

    $this->actingAs(staffUser('admin'))->put('/ar/admin/seo/users/1', [])->assertNotFound();
});

it('passes the overrides card to the edit screens', function () {
    $product = makeSimpleProduct();

    $this->actingAs(staffUser('admin'))->get(route('admin.products.edit', $product))
        ->assertInertia(fn ($page) => $page->where('seo_overrides.update_url', route('admin.seo.update', ['type' => 'products', 'id' => $product->id]))
            ->has('seo_overrides.urls.en'));
});

it('saves the SEO settings and rejects markup in verification codes', function () {
    $admin = staffUser('admin');

    $this->actingAs($admin)->put(route('admin.settings.update', 'seo'), [
        'home_title' => ['ar' => 'متجر'], 'allow_indexing' => true,
        'google_verification' => '"><script>', 'ga_measurement_id' => 'UA-123',
    ])->assertSessionHasErrors(['google_verification', 'ga_measurement_id']);

    $this->actingAs($admin)->put(route('admin.settings.update', 'seo'), [
        'home_title' => ['ar' => 'متجر'], 'home_description' => ['ar' => 'حلويات'], 'allow_indexing' => true,
        'default_description' => ['en' => 'Sweets and baking supplies'],
        'twitter_username' => '@ovalete', 'google_verification' => 'abc_123', 'ga_measurement_id' => 'G-AB12CD34',
    ])->assertSessionHasNoErrors();

    $seo = app(SeoSettings::class)->refresh();
    expect($seo->twitter_username)->toBe('ovalete')
        ->and($seo->ga_measurement_id)->toBe('G-AB12CD34')
        ->and($seo->default_description)->toBe(['en' => 'Sweets and baking supplies']);
});

it('shows the setup guide until Google Analytics is connected, and needs analytics.view', function () {
    config(['analytics.property_id' => null]);

    $this->actingAs(staffUser('admin'))->get(route('admin.analytics.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Analytics/Index')->where('configured', false)
            ->where('credentials_path', 'storage/app/analytics/service-account-credentials.json'));

    $this->actingAs(staffUser('order-manager'))->get(route('admin.analytics.index'))->assertForbidden();
    expect(app(SiteAnalytics::class)->report(28))->toBe(['status' => 'not_configured']);
});

it('turns every laravel-analytics report into the page data', function () {
    config(['analytics.property_id' => '123456', 'analytics.service_account_credentials_json' => ['type' => 'service_account']]);
    // The fake answers every package call with the same rows: one row carrying every dimension/metric used.
    Analytics::fake(collect([[
        'date' => now()->startOfDay(), 'pageTitle' => 'الرئيسية', 'fullPageUrl' => 'ovalete.test/ar', 'pageReferrer' => 'https://www.google.com/',
        'activeUsers' => 12, 'totalUsers' => 15, 'screenPageViews' => 40, 'sessions' => 20, 'engagementRate' => 0.55,
        'averageSessionDuration' => 95.4, 'bounceRate' => 0.45, 'newVsReturning' => 'new', 'country' => 'Palestine',
        'browser' => 'Chrome', 'operatingSystem' => 'Android', 'sessionDefaultChannelGroup' => 'Organic Search', 'deviceCategory' => 'mobile',
    ]]));

    $report = app(SiteAnalytics::class)->report(7);

    expect($report['status'])->toBe('ok')
        ->and($report['kpis'])->toMatchArray(['visitors' => 15, 'views' => 40, 'engagement_rate' => 55.0, 'avg_duration' => 95])
        ->and($report['trend'])->toHaveCount(8)
        ->and($report['trend'][7]['visitors'])->toBe(12)
        ->and($report['pages'][0]['url'])->toBe('ovalete.test/ar')
        ->and($report['referrers'][0]['label'])->toBe('www.google.com')
        ->and($report['countries'][0])->toBe(['label' => 'Palestine', 'views' => 40])
        ->and($report['channels'][0])->toBe(['label' => 'Organic Search', 'value' => 20])
        ->and($report['user_types'][0]['label'])->toBe('new');

    Cache::put('analytics:realtime', 3, 60);
    expect(app(SiteAnalytics::class)->summary())->toMatchArray(['status' => 'ok', 'realtime' => 3]);
});

it('loads gtag.js on storefront pages only, and allows it in the CSP only then', function () {
    config(['analytics.tracking' => true, 'csp.enabled' => true]); // CSP is off by default in phpunit.xml
    $settings = app(SeoSettings::class);
    $settings->ga_measurement_id = 'G-TEST1234';
    $settings->save();

    $store = $this->get(route('storefront.home'));
    expect($store->getContent())->toContain('googletagmanager.com/gtag/js?id=G-TEST1234')
        ->and($store->headers->get('Content-Security-Policy'))->toContain('https://www.googletagmanager.com');

    expect($this->actingAs(staffUser('admin'))->get(route('admin.dashboard'))->getContent())->not->toContain('googletagmanager');

    config(['analytics.tracking' => false]);
    $off = $this->get(route('storefront.home'));
    expect($off->getContent())->not->toContain('googletagmanager')
        ->and((string) $off->headers->get('Content-Security-Policy'))->not->toContain('googletagmanager');
});
