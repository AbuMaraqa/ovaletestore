<?php

use App\Models\Product;
use App\Support\StagedUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** The product screen: one save stores the product, its staged images and its search-engine overrides. */
function productPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'simple',
        'status' => 'published',
        'translations' => ['ar' => ['name' => 'شوكولاتة داكنة', 'description' => '<p>وصف <strong>غني</strong></p><script>alert(1)</script>']],
        'variants' => [['regular_price' => '25.00', 'stock_status' => 'in_stock']],
    ], $overrides);
}

it('creates a product with the images picked on the same screen, in their order', function () {
    Storage::fake('local');
    Storage::fake('public');
    $user = staffUser('product-manager');
    $this->actingAs($user);

    $first = $this->postJson(route('admin.uploads.store'), ['file' => UploadedFile::fake()->image('a.jpg', 800, 800)])->json('id');
    $second = $this->postJson(route('admin.uploads.store'), ['file' => UploadedFile::fake()->image('b.png', 600, 600)])->json('id');

    $this->post(route('admin.products.store'), productPayload(['uploads' => [$second, $first]]))
        ->assertRedirect()->assertSessionHasNoErrors();

    $product = Product::query()->latest('id')->first();
    expect($product->getMedia('gallery'))->toHaveCount(2)
        ->and($product->getFirstMedia('gallery')->mime_type)->toBe('image/png') // the order the staff member chose
        ->and($product->translate('ar')->description)->toBe('<p>وصف <strong>غني</strong></p>') // rich text, sanitised
        ->and(StagedUploads::path($user, $first))->toBeNull();
});

it('saves the search-engine overrides with the product, share image included', function () {
    Storage::fake('local');
    Storage::fake('public');
    $user = staffUser('product-manager');
    $this->actingAs($user);
    $image = $this->postJson(route('admin.uploads.store'), ['file' => UploadedFile::fake()->image('share.jpg', 1200, 630)])->json('id');

    $this->post(route('admin.products.store'), productPayload([
        'seo' => ['robots' => 'noindex,follow', 'canonical_url' => 'https://example.com/x', 'image_upload' => $image],
    ]))->assertSessionHasNoErrors();

    $seo = Product::query()->latest('id')->first()->seo;
    expect($seo->robots)->toBe('noindex,follow')->and($seo->canonical_url)->toBe('https://example.com/x');
    Storage::disk('public')->assertExists($seo->image);
});

it('goes back to a fresh add screen with "save & add another"', function () {
    $this->actingAs(staffUser('product-manager'))
        ->post(route('admin.products.store'), productPayload(['add_another' => true]))
        ->assertRedirect(route('admin.products.create'));
});

it('names fields readably in validation messages (the error summary shows them as they are)', function () {
    $this->actingAs(staffUser('product-manager'))
        ->post(route('admin.products.store'), productPayload(['translations' => ['ar' => ['name' => '']], 'variants' => [['regular_price' => '']]]))
        ->assertSessionHasErrors([
            'translations.ar.name' => __('validation.required', ['attribute' => __('admin.name').' (العربية)']),
            'variants.0.regular_price' => __('validation.required', ['attribute' => __('admin.regular_price')]),
        ]);
});

it('rejects staged ids that are not the user\'s own', function () {
    Storage::fake('local');
    $other = staffUser('admin');
    $id = StagedUploads::put(UploadedFile::fake()->image('x.jpg', 400, 400), $other);

    $this->actingAs(staffUser('product-manager'))
        ->post(route('admin.products.store'), productPayload(['uploads' => [$id]]))
        ->assertSessionHasErrors('uploads.0');
});

it('prunes staged files nobody saved', function () {
    Storage::fake('local');
    $user = staffUser('product-manager');
    $old = StagedUploads::put(UploadedFile::fake()->image('old.jpg', 400, 400), $user);
    touch(StagedUploads::path($user, $old), now()->subHours(25)->getTimestamp()); // age is the file's mtime
    $fresh = StagedUploads::put(UploadedFile::fake()->image('new.jpg', 400, 400), $user);

    expect(StagedUploads::prune(24))->toBe(1)
        ->and(StagedUploads::path($user, $old))->toBeNull()
        ->and(StagedUploads::path($user, $fresh))->not->toBeNull();
});
