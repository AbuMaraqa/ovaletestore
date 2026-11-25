<?php

use App\Settings\GeneralSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function fontFile(string $name = 'magnit-regular.woff2', string $magic = 'wOF2'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $magic.str_repeat("\0", 256));
}

function generalPayload(array $overrides = []): array
{
    return [
        '_method' => 'put',
        'site_name' => ['ar' => 'أوفاليت ستور', 'en' => 'Ovalete Store', 'he' => 'Ovalete Store'],
        'tagline' => ['ar' => 'كل مستلزمات الحلويات'],
        'font_family' => 'custom',
        'font_name' => 'OT Magnit Arabic',
        'font_scope' => 'all',
        'font_locales' => ['ar'],
        ...$overrides,
    ];
}

beforeEach(fn () => Storage::fake('public'));

it('uses the bundled Tajawal until a custom font is chosen', function () {
    expect(app(GeneralSettings::class)->font_family)->toBe('tajawal');

    $this->get(route('storefront.home'))->assertOk()->assertDontSee('@font-face', false);
});

it('uploads a licensed font in settings and serves it on the store and the admin', function () {
    $this->actingAs(staffUser('admin'));

    $this->post(route('admin.settings.update', 'general'), generalPayload([
        'font_regular' => fontFile(),
        'font_bold' => fontFile('magnit-bold.ttf', "\x00\x01\x00\x00"),
    ]))->assertSessionHasNoErrors();

    $settings = app(GeneralSettings::class)->refresh();
    expect($settings->font_family)->toBe('custom')
        ->and($settings->font_name)->toBe('OT Magnit Arabic')
        ->and($settings->font_files[400])->toEndWith('.woff2')
        ->and($settings->font_files[700])->toEndWith('.ttf');
    Storage::disk('public')->assertExists($settings->font_files[400]);

    $this->get(route('storefront.home'))->assertOk()
        ->assertSee("font-family:'OT Magnit Arabic'", false)
        ->assertSee("--font-sans:'OT Magnit Arabic', 'Tajawal'", false)
        ->assertSee("format('woff2')", false)
        ->assertSee('rel="preload"', false);
    $this->get(route('admin.dashboard'))->assertOk()->assertSee("--font-sans:'OT Magnit Arabic'", false);
});

it('keeps the admin on Tajawal when the font is for the store only', function () {
    $this->actingAs(staffUser('admin'));
    $this->post(route('admin.settings.update', 'general'), generalPayload(['font_regular' => fontFile(), 'font_scope' => 'storefront']))
        ->assertSessionHasNoErrors();

    $this->get(route('storefront.home'))->assertSee("--font-sans:'OT Magnit Arabic'", false);
    $this->get(route('admin.dashboard'))->assertDontSee('--font-sans:', false);
});

it('rejects files that are not fonts, unsafe names, and a custom font without its regular weight', function () {
    $this->actingAs(staffUser('admin'));

    $this->post(route('admin.settings.update', 'general'), generalPayload(['font_regular' => fontFile('evil.woff2', "\x89PNG")]))
        ->assertSessionHasErrors('font_regular');
    $this->post(route('admin.settings.update', 'general'), generalPayload(['font_regular' => fontFile('x.php', 'wOF2')]))
        ->assertSessionHasErrors('font_regular');
    $this->post(route('admin.settings.update', 'general'), generalPayload(['font_name' => "x'}body{display:none", 'font_regular' => fontFile()]))
        ->assertSessionHasErrors('font_name');
    $this->post(route('admin.settings.update', 'general'), generalPayload())
        ->assertSessionHasErrors('font_regular');

    expect(app(GeneralSettings::class)->refresh()->font_family)->toBe('tajawal')
        ->and(Storage::disk('public')->allFiles('fonts'))->toBe([]);
});

it('switches back to Tajawal without deleting the uploaded files', function () {
    $this->actingAs(staffUser('admin'));
    $this->post(route('admin.settings.update', 'general'), generalPayload(['font_regular' => fontFile()]));
    $this->post(route('admin.settings.update', 'general'), generalPayload(['font_family' => 'tajawal']))->assertSessionHasNoErrors();

    $this->get(route('storefront.home'))->assertDontSee('@font-face', false);
    expect(Storage::disk('public')->allFiles('fonts'))->toHaveCount(1);
});

it('shows an Arabic font on the Arabic pages only; English and Hebrew keep Tajawal', function () {
    $this->actingAs(staffUser('admin'));
    $this->post(route('admin.settings.update', 'general'), generalPayload(['font_regular' => fontFile()]))->assertSessionHasNoErrors();
    expect(app(GeneralSettings::class)->refresh()->font_locales)->toBe(['ar']);

    $this->get(route('storefront.home'))->assertSee("--font-sans:'OT Magnit Arabic'", false);

    $this->useRouteLocale('en');
    $this->get(route('storefront.home'))->assertOk()->assertDontSee('--font-sans:', false);
    $this->useRouteLocale('he');
    $this->get(route('storefront.home'))->assertOk()->assertDontSee('--font-sans:', false);
});
