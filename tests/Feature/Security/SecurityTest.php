<?php

use App\Migration\Woo\RemoteImageDownloader;
use App\Models\User;
use Illuminate\Http\UploadedFile;

it('sends baseline security headers', function () {
    $this->get('/ar')->assertHeader('X-Content-Type-Options', 'nosniff')
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
    $this->get('/ar')->assertOk()->assertDontSee('admin.products.update', false);
});

it('marks POS responses as non-cacheable', function () {
    $this->actingAs(staffUser('sales-rep'))->get(route('pos.index'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('signs out a deactivated user on their next request', function () {
    $rep = staffUser('sales-rep');
    $this->actingAs($rep)->get(route('pos.index'))->assertOk();

    $rep->update(['is_active' => false]);

    $this->get(route('pos.index'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('requires the current password to change the account e-mail', function () {
    $user = User::factory()->create(['email' => 'old@example.com', 'phone' => '0599000000']);
    $this->actingAs($user);
    $payload = ['name' => 'Customer', 'email' => 'new@example.com', 'phone' => '0599000000'];

    $this->put(route('account.profile.update'), $payload)->assertSessionHasErrors('current_password');
    $this->put(route('account.profile.update'), [...$payload, 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
    expect($user->fresh()->email)->toBe('old@example.com');

    $this->put(route('account.profile.update'), [...$payload, 'current_password' => 'password'])->assertSessionHasNoErrors();
    expect($user->fresh()->email)->toBe('new@example.com');
});

it('updates name and phone without a password when the e-mail is unchanged', function () {
    $user = User::factory()->create(['email' => 'same@example.com']);

    $this->actingAs($user)->put(route('account.profile.update'), ['name' => 'Renamed', 'email' => 'SAME@example.com', 'phone' => '0599 111 222'])
        ->assertSessionHasNoErrors();

    expect($user->fresh())->name->toBe('Renamed')->phone->toBe('0599111222');
});

it('only downloads migration images over https from the source store host', function (string $url, bool $allowed) {
    config(['services.woocommerce.base_url' => 'https://ovaletestore.com']);
    $call = fn () => RemoteImageDownloader::assertAllowedSource($url);

    $allowed ? expect($call)->not->toThrow(RuntimeException::class) : expect($call)->toThrow(RuntimeException::class);
})->with([
    ['https://ovaletestore.com/wp-content/uploads/a.jpg', true],
    ['https://www.ovaletestore.com/wp-content/uploads/a.jpg', true],
    ['//ovaletestore.com/a.jpg', true],
    ['http://ovaletestore.com/a.jpg', false],
    ['https://169.254.169.254/latest/meta-data', false],
    ['https://127.0.0.1/a.jpg', false],
    ['https://ovaletestore.com.evil.com/a.jpg', false],
    ['file:///etc/passwd', false],
]);
