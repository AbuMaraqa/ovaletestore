<?php

use App\Actions\Products\AttachProductImages;
use App\Migration\Woo\RemoteImageDownloader;
use App\Models\User;
use App\Support\StagedUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
    Storage::fake('local');
    $this->actingAs(staffUser('product-manager'));

    $fake = UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "pwned"; ?>');

    $this->postJson(route('admin.uploads.store'), ['file' => $fake])->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('stages a real image and attaches it on save under a generated name', function () {
    Storage::fake('local');
    $product = makeSimpleProduct(overrides: ['translations' => ['en' => ['name' => 'Gallery Product']]]);
    $user = staffUser('product-manager');
    $this->actingAs($user);

    $id = $this->postJson(route('admin.uploads.store'), ['file' => UploadedFile::fake()->image('../../evil name.png', 800, 800)])
        ->assertCreated()->json('id');

    app(AttachProductImages::class)->handle($product, $user, [$id]);

    $media = $product->fresh()->getFirstMedia('gallery');
    expect($media)->not->toBeNull()
        ->and($media->file_name)->toStartWith('gallery-product-')
        ->and($media->file_name)->not->toContain('..')
        ->and(StagedUploads::path($user, $id))->toBeNull(); // moved, not copied
});

it('never lets one user claim a file another user staged', function () {
    Storage::fake('local');
    $owner = staffUser('product-manager');
    $id = $this->actingAs($owner)->postJson(route('admin.uploads.store'), ['file' => UploadedFile::fake()->image('a.png', 400, 400)])->json('id');

    expect(StagedUploads::path(staffUser('admin'), $id))->toBeNull()
        ->and(StagedUploads::path($owner, $id))->not->toBeNull();

    $this->actingAs(staffUser('order-manager'))->postJson(route('admin.uploads.store'), ['file' => UploadedFile::fake()->image('b.png', 400, 400)])->assertForbidden();
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
