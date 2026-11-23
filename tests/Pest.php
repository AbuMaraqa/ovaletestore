<?php

use App\Actions\Products\SaveProduct;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ShippingMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Honeypot\Honeypot;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        Storage::fake('public');
        $this->seed([RolesAndPermissionsSeeder::class, ShippingMethodSeeder::class]);
        $this->useRouteLocale('ar'); // every page URL is locale-prefixed (/ar, /en, /he)
    })
    ->in('Feature');

/** Creates a published simple product through the real SaveProduct action. Price in major units. */
function makeSimpleProduct(string $name = 'منتج تجريبي', string $price = '25.00', array $variant = [], array $overrides = []): Product
{
    return app(SaveProduct::class)->handle(array_replace_recursive([
        'type' => 'simple',
        'status' => 'published',
        'translations' => ['ar' => ['name' => $name], 'en' => ['name' => 'Test product '.uniqid()]],
        'variants' => [array_merge([
            'sku' => null,
            'regular_price' => (int) round(((float) $price) * 100),
            'stock_status' => 'in_stock',
        ], $variant)],
    ], $overrides));
}

/** A global attribute with terms, e.g. makeAttribute('weight', ['1 كغ', '5 كغ']). */
function makeAttribute(string $code, array $termNames): Attribute
{
    $attribute = Attribute::query()->create(['code' => $code, 'display_type' => 'button', 'ar' => ['name' => $code]]);

    foreach (array_values($termNames) as $i => $name) {
        $attribute->terms()->create(['code' => "{$code}-{$i}", 'sort_order' => $i, 'ar' => ['name' => $name]]);
    }

    return $attribute->load('terms');
}

function staffUser(string $role): User
{
    return User::factory()->create(['is_active' => true])->assignRole($role);
}

/**
 * Honeypot fields exactly as the real forms send them (empty trap field + encrypted timestamp).
 * The honeypot is required on checkout and registration, so a POST without them is treated as spam.
 *
 * @return array<string, string>
 */
function honeypotFields(): array
{
    $honeypot = app(Honeypot::class)->toArray();

    return [$honeypot['nameFieldName'] => '', $honeypot['validFromFieldName'] => $honeypot['encryptedValidFrom']];
}

/** A valid checkout form (west-bank delivery) including the honeypot fields. */
function checkoutPayload(): array
{
    return [
        'customer_name' => 'محمد أحمد',
        'customer_phone' => '0599 123 456',
        'shipping_method_id' => ShippingMethod::query()->where('code', 'west_bank')->value('id'),
        'shipping_city' => 'رام الله',
        'shipping_address' => 'شارع الإرسال 12',
        ...honeypotFields(),
    ];
}

/** A valid registration form including the honeypot fields. */
function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'سارة',
        'email' => 'sara@example.com',
        'phone' => '0599 111 222',
        'password' => 'Secret-Pass-123',
        'password_confirmation' => 'Secret-Pass-123',
        ...honeypotFields(),
    ], $overrides);
}
