<?php

use App\Models\Product;
use App\Models\User;

it('redirects guests from the admin to login', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

it('forbids authenticated users without a staff role', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
});

it('forbids deactivated staff', function () {
    $user = staffUser('admin');
    $user->update(['is_active' => false]);

    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
});

it('lets a product manager manage products but not users, settings or orders', function () {
    $this->actingAs(staffUser('product-manager'));

    $this->get(route('admin.products.index'))->assertOk();
    $this->get(route('admin.categories.index'))->assertOk();
    $this->get(route('admin.users.index'))->assertForbidden();
    $this->get(route('admin.settings.edit', 'general'))->assertForbidden();
    $this->get(route('admin.orders.index'))->assertForbidden();
});

it('lets an order manager view orders but not edit products', function () {
    $product = makeSimpleProduct();
    $this->actingAs(staffUser('order-manager'));

    $this->get(route('admin.orders.index'))->assertOk();
    $this->put(route('admin.products.update', $product), [])->assertForbidden();
    $this->delete(route('admin.products.destroy', $product))->assertForbidden();
});

it('never allows granting super-admin through the UI', function () {
    $this->actingAs(staffUser('admin'));

    $this->post(route('admin.users.store'), [
        'name' => 'Mallory', 'email' => 'mallory@example.com',
        'password' => 'Secret-Pass-123', 'password_confirmation' => 'Secret-Pass-123',
        'roles' => ['super-admin'],
    ])->assertSessionHasErrors('roles.0');

    expect(User::query()->where('email', 'mallory@example.com')->exists())->toBeFalse();
});

it('does not let an admin edit a super-admin account', function () {
    $super = staffUser('super-admin');
    $this->actingAs(staffUser('admin'))->get(route('admin.users.edit', $super))->assertForbidden();
});

it('creates a product through the admin endpoint with server-side validation', function () {
    $this->actingAs(staffUser('product-manager'));

    $this->post(route('admin.products.store'), ['type' => 'simple', 'status' => 'published', 'translations' => ['ar' => ['name' => '']], 'variants' => [[]]])
        ->assertSessionHasErrors(['translations.ar.name', 'variants.0.regular_price', 'variants.0.stock_status']);

    $this->post(route('admin.products.store'), [
        'type' => 'simple',
        'status' => 'published',
        'translations' => ['ar' => ['name' => 'زبدة', 'description' => '<p>جيدة</p><script>alert(1)</script>'], 'en' => ['name' => 'Butter']],
        'variants' => [['regular_price' => '15.90', 'stock_status' => 'in_stock']],
    ])->assertRedirect();

    $product = Product::query()->with('translations', 'defaultVariant')->sole();
    expect($product->defaultVariant->regular_price)->toBe(1590)
        ->and($product->translate('ar')->description)->toBe('<p>جيدة</p>'); // XSS stripped
});

it('shares an initials avatar rendered as text (site font) when no photo is uploaded', function () {
    $user = staffUser('admin');
    $user->update(['name' => 'مدير النظام']);

    $this->actingAs($user)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page
        ->where('auth.user.avatar.url', null)
        ->where('auth.user.avatar.initials', 'ما')
        ->has('auth.user.avatar.color'));
});
