<?php

use Illuminate\Http\UploadedFile;

it('sends baseline security headers', function () {
    $this->get('/')->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('marks admin responses as non-cacheable', function () {
    $this->actingAs(staffUser('admin'))->get(route('admin.dashboard'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('rejects non-image uploads disguised with an image extension', function () {
    $product = makeSimpleProduct();
    $this->actingAs(staffUser('product-manager'));

    $fake = UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "pwned"; ?>');

    $this->post(route('admin.products.media.store', $product), ['images' => [$fake]])->assertSessionHasErrors('images.0');
    expect($product->fresh()->getMedia('gallery'))->toHaveCount(0);
});

it('accepts a real image upload and stores it under a generated name', function () {
    $product = makeSimpleProduct(overrides: ['translations' => ['en' => ['name' => 'Gallery Product']]]);
    $this->actingAs(staffUser('product-manager'));

    $this->post(route('admin.products.media.store', $product), ['images' => [UploadedFile::fake()->image('../../evil name.png', 800, 800)]])
        ->assertSessionHasNoErrors();

    $media = $product->fresh()->getFirstMedia('gallery');
    expect($media)->not->toBeNull()
        ->and($media->file_name)->toStartWith('gallery-product-')
        ->and($media->file_name)->not->toContain('..');
});

it('throttles repeated login attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong']);
    }

    $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertStatus(429);
});

it('does not expose the admin route map to storefront visitors', function () {
    $this->get('/')->assertOk()->assertDontSee('admin.products.update', false);
});
