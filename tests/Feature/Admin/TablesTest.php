<?php

use App\Enums\ProductStatus;
use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use App\Tables\Tables;

/** The unified admin table system: every list page gets its definition, bulk actions and CSV export. */
it('gives every admin list page its table definition', function () {
    $admin = staffUser('admin');
    $routes = [
        'admin.products.index', 'admin.categories.index', 'admin.attributes.index', 'admin.brands.index', 'admin.orders.index',
        'admin.customers.index', 'admin.traders.index', 'admin.price-tiers.index', 'admin.companies.index',
        'admin.shipping-methods.index', 'admin.currencies.index', 'admin.users.index', 'admin.roles.index', 'admin.activity.index',
    ];

    foreach ($routes as $name) {
        $this->actingAs($admin)->get(route($name))->assertOk()
            ->assertInertia(fn ($page) => $page->has('table.columns')->has('state')->where('table.bulk_url', fn ($url) => is_string($url)));
    }
    expect(Tables::ALL)->toHaveCount(count($routes));
});

it('runs a bulk action row by row and reports the result', function () {
    $a = makeSimpleProduct('أ');
    $b = makeSimpleProduct('ب');

    $this->actingAs(staffUser('admin'))
        ->post(route('admin.tables.bulk', 'products'), ['action' => 'feature', 'ids' => [$a->id, $b->id]])
        ->assertRedirect()->assertSessionHas('success');

    expect(Product::query()->whereKey([$a->id, $b->id])->where('is_featured', true)->count())->toBe(2);

    $this->actingAs(staffUser('admin'))
        ->post(route('admin.tables.bulk', 'products'), ['action' => 'draft', 'ids' => [$a->id]]);
    expect($a->fresh()->status)->toBe(ProductStatus::Draft);
});

it('checks the bulk action permission, not only the list permission', function () {
    $product = makeSimpleProduct();

    // content-manager may view and update products, but not delete them.
    $this->actingAs(staffUser('content-manager'))
        ->post(route('admin.tables.bulk', 'products'), ['action' => 'delete', 'ids' => [$product->id]])
        ->assertForbidden();
    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue();

    $this->actingAs(staffUser('order-manager'))
        ->post(route('admin.tables.bulk', 'brands'), ['action' => 'delete', 'ids' => [1]])
        ->assertForbidden();
});

it('skips rows a bulk action may not touch, with the reason', function () {
    $base = Currency::query()->where('is_default', true)->sole();

    $this->actingAs(staffUser('admin'))
        ->post(route('admin.tables.bulk', 'currencies'), ['action' => 'delete', 'ids' => [$base->id]])
        ->assertSessionHas('error');

    expect($base->fresh())->not->toBeNull();
});

it('never lets bulk change the acting user or a more powerful account', function () {
    $admin = staffUser('admin');
    $owner = staffUser('super-admin');

    $this->actingAs($admin)->post(route('admin.tables.bulk', 'users'), ['action' => 'deactivate', 'ids' => [$admin->id, $owner->id]]);

    expect($admin->fresh()->is_active)->toBeTrue()
        ->and($owner->fresh()->is_active)->toBeTrue();
});

it('rejects unknown tables and actions', function () {
    $admin = staffUser('admin');

    $this->actingAs($admin)->post(route('admin.tables.bulk', 'nope'), ['action' => 'delete', 'ids' => [1]])->assertNotFound();
    $this->actingAs($admin)->post(route('admin.tables.bulk', 'products'), ['action' => 'explode', 'ids' => [1]])->assertNotFound();
    $this->actingAs($admin)->post(route('admin.tables.bulk', 'products'), ['action' => 'feature', 'ids' => []])->assertSessionHasErrors('ids');
});

it('exports the filtered rows (or only the selection) as CSV', function () {
    $a = makeSimpleProduct('منتج أول');
    makeSimpleProduct('منتج ثاني');

    $all = $this->actingAs(staffUser('admin'))->get(route('admin.tables.export', 'products'));
    $all->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $all->streamedContent();
    expect($csv)->toStartWith("\u{FEFF}")->toContain('منتج أول')->toContain('منتج ثاني');

    $one = $this->actingAs(staffUser('admin'))->get(route('admin.tables.export', ['table' => 'products', 'filter' => ['ids' => (string) $a->id]]))->streamedContent();
    expect($one)->toContain('منتج أول')->not->toContain('منتج ثاني');
});

it('requires the list permission to export', function () {
    $this->actingAs(staffUser('content-manager'))->get(route('admin.tables.export', 'orders'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.tables.export', 'products'))->assertForbidden();
});
