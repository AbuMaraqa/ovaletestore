<?php

use App\Actions\Products\SaveProduct;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);
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
