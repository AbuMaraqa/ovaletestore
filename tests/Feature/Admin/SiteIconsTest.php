<?php

use App\Settings\GeneralSettings;
use App\Support\SiteIcons;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

it('generates the favicon set and links it from every page', function () {
    expect(SiteIcons::generate())->toBeTrue();

    foreach (['favicon.ico', 'favicon-32.png', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png'] as $file) {
        Storage::disk('public')->assertExists(SiteIcons::DIR.'/'.$file);
    }
    $ico = (string) Storage::disk('public')->get(SiteIcons::DIR.'/favicon.ico');
    expect(substr($ico, 0, 4))->toBe("\x00\x00\x01\x00"); // ICO header

    $this->get(route('storefront.home'))->assertOk()
        ->assertSee('favicon.ico?v=', false)
        ->assertSee('rel="apple-touch-icon"', false)
        ->assertSee('rel="manifest"', false);
});

it('serves a web app manifest with the home-screen icons', function () {
    SiteIcons::generate();

    $this->get(route('manifest'))->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('theme_color', '#2b1d17')
        ->assertJsonCount(2, 'icons');
});

it('regenerates the icons when a new logo is uploaded', function () {
    $this->actingAs(staffUser('admin'));
    $this->post(route('admin.settings.update', 'general'), [
        '_method' => 'put',
        'site_name' => ['ar' => 'أوفاليت ستور', 'en' => 'Ovalete Store'],
        'tagline' => ['ar' => 'كل مستلزمات الحلويات'],
        'font_family' => 'tajawal', 'font_scope' => 'all',
        'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
    ])->assertSessionHasNoErrors();

    expect(app(GeneralSettings::class)->refresh()->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists(SiteIcons::DIR.'/apple-touch-icon.png');
});
