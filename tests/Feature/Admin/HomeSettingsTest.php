<?php

use App\Settings\HomeSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Settings → Homepage: the hero photo (WebP sizes) and the hero texts are managed from the panel. */
function homePayload(array $overrides = []): array
{
    return array_replace_recursive([
        '_method' => 'put',
        'hero_title' => ['ar' => 'كل ما تحتاجه للحلويات', 'en' => 'Everything for pastry', 'he' => ''],
        'hero_subtitle' => ['ar' => 'شوكولاتة وحشوات وقهوة', 'en' => '', 'he' => ''],
    ], $overrides);
}

it('replaces the hero photo with WebP sizes and updates the texts the homepage shows', function () {
    Storage::fake('public');
    $home = app(HomeSettings::class);
    Storage::disk('public')->put('home/hero-960.webp', 'old');
    $home->hero_image = ['960' => 'home/hero-960.webp'];
    $home->save();

    $this->actingAs(staffUser('admin'))
        ->post(route('admin.settings.update', 'home'), homePayload(['hero_image' => UploadedFile::fake()->image('cake.jpg', 2400, 1600)]))
        ->assertSessionHasNoErrors();

    $home = app(HomeSettings::class)->refresh();
    expect(array_keys($home->hero_image))->toBe([960, 1920]) // PHP keeps numeric keys as integers
        ->and($home->hero_title['ar'])->toBe('كل ما تحتاجه للحلويات');
    foreach ($home->hero_image as $path) {
        Storage::disk('public')->assertExists($path);
        expect($path)->toEndWith('.webp');
    }
    Storage::disk('public')->assertMissing('home/hero-960.webp'); // the previous photo is removed

    $this->get(route('storefront.home'))->assertInertia(fn ($page) => $page
        ->where('hero.title', 'كل ما تحتاجه للحلويات')
        ->where('hero.image.src', fn (string $src) => str_contains($src, $home->hero_image['1920'])));
});

it('keeps the current photo when only the texts change, and rejects small or non-image files', function () {
    Storage::fake('public');
    $home = app(HomeSettings::class);
    $home->hero_image = ['960' => 'home/a-960.webp', '1920' => 'home/a-1920.webp'];
    $home->save();
    $admin = staffUser('admin');

    $this->actingAs($admin)->post(route('admin.settings.update', 'home'), homePayload())->assertSessionHasNoErrors();
    expect(app(HomeSettings::class)->refresh()->hero_image['1920'])->toBe('home/a-1920.webp');

    $this->post(route('admin.settings.update', 'home'), homePayload(['hero_image' => UploadedFile::fake()->image('small.jpg', 600, 400)]))
        ->assertSessionHasErrors('hero_image');
    $this->post(route('admin.settings.update', 'home'), homePayload(['hero_title' => ['ar' => '']]))
        ->assertSessionHasErrors('hero_title.ar');
});

it('needs the homepage settings permission', function () {
    $this->actingAs(staffUser('order-manager'))->get(route('admin.settings.edit', 'home'))->assertForbidden();
    $this->actingAs(staffUser('admin'))->get(route('admin.settings.edit', 'home'))->assertOk();
});
