<?php

use App\Models\StoreVisit;
use App\Support\Analytics\StoreVisits;
use Illuminate\Support\Facades\Cache;

/** Internal traffic counter (shetabit/visitor + TrackStoreVisit) and its report (StoreVisits). */
const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';
const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

beforeEach(function () {
    $this->withoutDefer();
    Cache::flush();
});

it('counts a real page view and keeps nothing personal', function () {
    $this->withHeaders(['User-Agent' => IPHONE, 'Referer' => 'https://www.google.com/', 'Cookie' => 'secret=1'])
        ->get('/ar/shop?utm_source=x')->assertOk();

    $visit = StoreVisit::query()->sole();
    expect($visit->path)->toBe('/ar/shop')
        ->and($visit->route_name)->toBe('storefront.shop')
        ->and($visit->channel)->toBe('Organic Search')
        ->and($visit->referrer_host)->toBe('google.com')
        ->and($visit->device_type)->toBe('mobile')
        ->and($visit->visitor_hash)->toHaveLength(16)
        ->and($visit->locale)->toBe('ar')
        // scrubbed: no headers (cookies), request input, IP or user agent
        ->and($visit->headers)->toBeNull()
        ->and($visit->request)->toBeNull()
        ->and($visit->ip)->toBeNull()
        ->and($visit->useragent)->toBeNull();
});

it('treats clicks inside the store as navigation, not as a new source', function () {
    $this->withHeaders(['User-Agent' => CHROME, 'Referer' => url('/ar')])->get('/ar/categories')->assertOk();
    $this->flushHeaders()->withHeaders(['User-Agent' => CHROME])->get('/ar/brands')->assertOk();

    expect(StoreVisit::query()->where('path', '/ar/categories')->value('channel'))->toBeNull()
        ->and(StoreVisit::query()->where('path', '/ar/brands')->value('channel'))->toBe('Direct');
});

it('ignores bots, staff, prefetches, partial reloads, redirects and helper requests', function () {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->get('/ar/shop')->assertOk();
    $this->flushHeaders()->withHeaders(['User-Agent' => CHROME, 'Purpose' => 'prefetch'])->get('/ar/shop')->assertOk();
    $this->flushHeaders()->withHeaders(['User-Agent' => CHROME, 'X-Inertia' => 'true', 'X-Inertia-Partial-Data' => 'products', 'X-Inertia-Partial-Component' => 'Storefront/Catalog/Index'])->get('/ar/shop');
    $this->flushHeaders()->withHeaders(['User-Agent' => CHROME])->get('/ar/contact')->assertStatus(301);
    $this->flushHeaders()->withHeaders(['User-Agent' => CHROME])->getJson(route('storefront.search.suggest', ['q' => 'قهوة']));
    $this->flushHeaders()->actingAs(staffUser('admin'))->withHeaders(['User-Agent' => CHROME])->get('/ar/shop')->assertOk();

    expect(StoreVisit::query()->count())->toBe(0);
});

it('builds the traffic report: visitors, sessions, bounce, pages with names, sources and devices', function () {
    $product = makeSimpleProduct(overrides: ['translations' => ['ar' => ['name' => 'قهوة إسبريسو']]]);
    $slug = $product->translate('ar')->slug;
    $row = fn (array $a) => StoreVisit::query()->create(['url' => 'http://x', 'extra_attributes' => [
        'path' => '/ar', 'visitor_hash' => 'v1', 'session_hash' => 's1', 'device_type' => 'mobile', 'locale' => 'ar', ...$a,
    ]] + ['route_name' => $a['route_name'] ?? 'storefront.home', 'browser' => 'Chrome', 'platform' => 'AndroidOS']);

    $row(['channel' => 'Organic Social', 'referrer_host' => 'instagram.com']);                       // s1 lands …
    $row(['path' => "/ar/product/{$slug}", 'subject' => $slug, 'route_name' => 'storefront.product']); // … and browses
    $row(['visitor_hash' => 'v2', 'session_hash' => 's2', 'channel' => 'Direct', 'device_type' => 'desktop']); // s2 bounces

    $report = app(StoreVisits::class)->report(7);

    expect($report['status'])->toBe('ok')
        ->and($report['source'])->toBe('internal')
        ->and($report['kpis'])->toMatchArray(['visitors' => 2, 'views' => 3, 'sessions' => 2, 'bounce_rate' => 50.0, 'engagement_rate' => 50.0])
        ->and($report['trend'])->toHaveCount(8)
        ->and(end($report['trend'])['views'])->toBe(3)
        ->and(collect($report['pages'])->pluck('title'))->toContain('قهوة إسبريسو', __('common.home'))
        ->and($report['channels'])->toContain(['label' => 'Organic Social', 'value' => 1], ['label' => 'Direct', 'value' => 1])
        ->and($report['devices'][0])->toBe(['label' => 'mobile', 'value' => 2])
        ->and($report)->not->toHaveKey('countries') // no IP is kept, so no countries
        ->and(app(StoreVisits::class)->realtime())->toBe(2);
});

it('prunes page views older than about 13 months', function () {
    $old = StoreVisit::query()->create(['url' => 'http://x', 'extra_attributes' => ['path' => '/ar']]);
    $old->forceFill(['created_at' => now()->subDays(StoreVisit::KEEP_DAYS + 1)])->save();
    StoreVisit::query()->create(['url' => 'http://x', 'extra_attributes' => ['path' => '/ar']]);

    $this->artisan('model:prune', ['--model' => [StoreVisit::class]])->assertSuccessful();

    expect(StoreVisit::query()->count())->toBe(1);
});
