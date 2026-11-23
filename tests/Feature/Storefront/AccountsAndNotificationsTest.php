<?php

use App\Models\Brand;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewOrderPlaced;
use Illuminate\Support\Facades\Notification;

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'سارة',
        'email' => 'sara@example.com',
        'phone' => '0599 111 222',
        'password' => 'Secret-Pass-123',
        'password_confirmation' => 'Secret-Pass-123',
    ], $overrides);
}

it('lets anyone register as a customer without admin access', function () {
    $this->post(route('register.store'), registrationPayload())->assertRedirect(route('account.dashboard'));

    $user = User::query()->where('email', 'sara@example.com')->sole();
    expect($user->hasRole('customer'))->toBeTrue()
        ->and($user->phone)->toBe('0599111222')
        ->and($user->can('access-admin'))->toBeFalse();

    $this->get(route('admin.dashboard'))->assertForbidden();
    $this->get(route('account.dashboard'))->assertOk();
});

it('sends customers to their account and staff to the admin after login', function () {
    $customer = User::factory()->create(['password' => 'Secret-Pass-123'])->assignRole('customer');
    $this->post(route('login.store'), ['email' => $customer->email, 'password' => 'Secret-Pass-123'])->assertRedirect(route('account.dashboard'));

    auth()->logout();
    $staff = staffUser('admin');
    $staff->update(['password' => 'Secret-Pass-123']);
    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'Secret-Pass-123'])->assertRedirect(route('admin.dashboard'));
});

it('links orders to the signed-in customer and hides other customers orders', function () {
    $product = makeSimpleProduct();
    $alice = User::factory()->create()->assignRole('customer');
    $bob = User::factory()->create()->assignRole('customer');

    $this->actingAs($alice)->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);
    $this->post(route('storefront.checkout.store'), checkoutPayload());
    $order = Order::query()->sole();

    expect($order->user_id)->toBe($alice->id);
    $this->get(route('account.orders.show', $order->number))->assertOk();
    $this->actingAs($bob)->get(route('account.orders.show', $order->number))->assertNotFound();
});

it('notifies staff (not customers) when an order is placed and exposes the count to the admin', function () {
    Notification::fake();
    $staff = staffUser('order-manager');
    $productManager = staffUser('product-manager');
    $customer = User::factory()->create()->assignRole('customer');
    $product = makeSimpleProduct();

    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 2]);
    $this->post(route('storefront.checkout.store'), checkoutPayload());

    Notification::assertSentTo($staff, NewOrderPlaced::class);
    Notification::assertNotSentTo([$customer, $productManager], NewOrderPlaced::class);
});

it('shows unread order notifications and the pending badge in admin shared props', function () {
    $staff = staffUser('admin');
    $product = makeSimpleProduct();
    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);
    $this->post(route('storefront.checkout.store'), checkoutPayload());

    $this->actingAs($staff)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page
        ->where('admin_badges.pending_orders', 1)
        ->where('admin_notifications.unread', 1)
        ->has('admin_notifications.items', 1));

    $id = $staff->notifications()->value('id');
    $this->patch(route('admin.notifications.read', $id))->assertRedirect(route('admin.orders.show', Order::query()->value('id')));
    expect($staff->fresh()->unreadNotifications()->count())->toBe(0);
});

it('lists only the products of a brand on the brand page', function () {
    $brand = Brand::query()->create(['is_active' => true, 'ar' => ['name' => 'لوتس'], 'en' => ['name' => 'Lotus']]);
    $lotus = makeSimpleProduct(name: 'بسكويت لوتس', overrides: ['brand_id' => $brand->id]);
    makeSimpleProduct(name: 'منتج آخر');

    $this->get(route('storefront.brand', ['slug' => $brand->translate('ar')->slug]))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Storefront/Catalog/Index')
        ->where('brand.name', 'لوتس')
        ->has('products.data', 1)
        ->where('products.data.0.id', $lotus->id));
});

it('returns live search suggestions as JSON', function () {
    makeSimpleProduct(name: 'شوكولاتة بلجيكية');

    $this->getJson(route('storefront.search.suggest', ['q' => 'شوكولاتة']))
        ->assertOk()
        ->assertJsonCount(1, 'products')
        ->assertJsonStructure(['products' => [['name', 'url', 'image', 'price']], 'categories', 'all_url']);
});
