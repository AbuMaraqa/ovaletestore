<?php

/*
| End-to-end checks of the store's business operations that were not covered elsewhere,
| plus regression tests for the bugs found in the 2026-11-24 QA/security review.
*/

use App\Actions\Cart\AddToCart;
use App\Enums\OrderStatus;
use App\Enums\StockStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Company;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Settings\StoreSettings;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

/** Fills the honeypot trap field the form actually sent (its name is randomised per page load). */
function asBot(array $payload): array
{
    $trap = collect(array_keys($payload))->first(fn ($k) => str_starts_with($k, (string) config('honeypot.name_field_name')));

    return [...$payload, $trap => 'I am a bot'];
}

function opsCompany(): Company
{
    return Company::query()->create(['name' => 'شركة '.uniqid(), 'phone' => '0599000000', 'city' => 'رام الله']);
}

// ── Orders: status workflow over HTTP ───────────────────────────────────────────────────────────

it('restocks a sold-out variant on cancel so it can be sold again, and flushes the POS catalog', function () {
    $order = placeWebOrder(2, variant: ['manage_stock' => true, 'stock_quantity' => 2]);
    $variant = ProductVariant::query()->findOrFail($order->items->first()->product_variant_id);
    expect($variant->fresh()->stock_status)->toBe(StockStatus::OutOfStock);

    Cache::put('pos.catalog.ar', ['stale'], 300);
    $this->actingAs(staffUser('order-manager'))
        ->patch(route('admin.orders.status', $order), ['status' => 'cancelled', 'note' => 'طلب الزبون الإلغاء'])
        ->assertSessionHasNoErrors();

    $fresh = $variant->fresh();
    expect($fresh->stock_quantity)->toBe(2)
        ->and($fresh->stock_status)->toBe(StockStatus::InStock)
        ->and($fresh->isPurchasable())->toBeTrue()
        ->and(Cache::has('pos.catalog.ar'))->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->notes()->value('body'))->toBe('طلب الزبون الإلغاء');

    app(AddToCart::class)->handle(Cart::query()->create(['token' => 'x']), $fresh, 1); // purchasable again: no exception
});

it('never restocks twice when the same order is cancelled twice', function () {
    $order = placeWebOrder(1, variant: ['manage_stock' => true, 'stock_quantity' => 5]);
    $manager = staffUser('order-manager');

    $this->actingAs($manager)->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])->assertSessionHasNoErrors();
    $this->actingAs($manager)->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])->assertSessionHasErrors('status');

    expect(ProductVariant::query()->find($order->items->first()->product_variant_id)->stock_quantity)->toBe(5);
});

it('lets only order staff change an order status and validates the transition', function () {
    $order = placeWebOrder();

    $this->actingAs(staffUser('product-manager'))->patch(route('admin.orders.status', $order), ['status' => 'confirmed'])->assertForbidden();
    $this->actingAs(staffUser('order-manager'))->patch(route('admin.orders.status', $order), ['status' => 'processing'])->assertSessionHasNoErrors();
    // Forward jumps are allowed; going back never is (stock/balances would be counted twice).
    $this->actingAs(staffUser('order-manager'))->patch(route('admin.orders.status', $order), ['status' => 'confirmed'])->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
});

it('shows the admin order list with filters and an order page', function () {
    $order = placeWebOrder();
    $this->actingAs(staffUser('order-manager'));

    $this->get(route('admin.orders.index', ['filter' => ['search' => $order->number, 'channel' => 'web']]))->assertOk();
    $this->get(route('admin.orders.show', $order))->assertOk();
});

// ── Cart and checkout ───────────────────────────────────────────────────────────────────────────

it('updates and removes cart lines; quantity 0 removes the line', function () {
    $product = makeSimpleProduct();
    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);
    $item = Cart::query()->sole()->items()->sole();

    $this->patch(route('storefront.cart.update', $item), ['quantity' => 3])->assertSessionHasNoErrors();
    expect($item->fresh()->quantity)->toBe(3);

    $this->patch(route('storefront.cart.update', $item), ['quantity' => 0]);
    expect($item->fresh())->toBeNull();

    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);
    $this->delete(route('storefront.cart.destroy', Cart::query()->sole()->items()->sole()))->assertRedirect();
    expect(Cart::query()->sole()->items()->count())->toBe(0);
});

it('drops a deleted product from the cart instead of breaking the cart and checkout pages', function () {
    $kept = makeSimpleProduct();
    $deleted = makeSimpleProduct();
    foreach ([$kept, $deleted] as $p) {
        $this->post(route('storefront.cart.store'), ['variant_id' => $p->defaultVariant->id, 'quantity' => 1]);
    }

    $deleted->delete(); // soft delete from the admin

    $this->get(route('storefront.cart'))->assertOk();
    $this->get(route('storefront.checkout'))->assertOk();
    expect(Cart::query()->sole()->items()->pluck('product_variant_id')->all())->toBe([$kept->defaultVariant->id]);
});

it('merges the guest cart into the customer cart on login', function () {
    $customer = User::factory()->create()->assignRole('customer');
    $product = makeSimpleProduct();
    Cart::query()->create(['token' => 'mine', 'user_id' => $customer->id])->items()->create(['product_variant_id' => $product->defaultVariant->id, 'quantity' => 2]);

    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]); // as guest
    $this->post(route('login'), ['email' => $customer->email, 'password' => 'password']);
    $this->get(route('storefront.cart'))->assertOk();

    expect(Cart::query()->count())->toBe(1)
        ->and(Cart::query()->sole()->items()->sole()->quantity)->toBe(3);
});

it('enforces the minimum order total and shows how much is missing', function () {
    $settings = app(StoreSettings::class);
    $settings->min_order_total = 5000;
    $settings->save();

    $product = makeSimpleProduct(price: '20.00');
    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);

    $this->get(route('storefront.cart'))->assertInertia(fn ($page) => $page
        ->where('cart.meets_minimum', false)
        ->where('cart.minimum_missing', Money::format(3000)));
    $this->post(route('storefront.checkout.store'), checkoutPayload())->assertSessionHasErrors();
    expect(Order::query()->count())->toBe(0);
});

it('requires login to check out when guest checkout is disabled', function () {
    $settings = app(StoreSettings::class);
    $settings->guest_checkout = false;
    $settings->save();

    $product = makeSimpleProduct();
    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);

    $this->get(route('storefront.checkout'))->assertRedirect(route('login'));
    $this->post(route('storefront.checkout.store'), checkoutPayload())->assertForbidden();
});

it('rejects a checkout that omits or fills the honeypot', function () {
    $product = makeSimpleProduct();
    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);
    $payload = checkoutPayload();
    $prefix = (string) config('honeypot.name_field_name');
    $withoutFields = collect($payload)->reject(fn ($v, $k) => str_starts_with($k, $prefix) || $k === config('honeypot.valid_from_field_name'))->all();

    $this->post(route('storefront.checkout.store'), $withoutFields);
    $this->post(route('storefront.checkout.store'), asBot($payload));

    expect(Order::query()->count())->toBe(0);
});

it('protects the order confirmation page with an expiring signature', function () {
    $order = placeWebOrder();

    $this->get(route('storefront.order.thanks', ['order' => $order->number]))->assertForbidden();
    $this->get(URL::temporarySignedRoute('storefront.order.thanks', now()->addHour(), ['order' => $order->number]))->assertOk();
    $this->get(URL::temporarySignedRoute('storefront.order.thanks', now()->subMinute(), ['order' => $order->number]))->assertForbidden();
});

it('shows a free-delivery progress bar when the delivery method has a threshold', function () {
    ShippingMethod::query()->where('code', 'inside')->update(['cost' => 2000, 'free_over' => 10000]);
    $product = makeSimpleProduct(price: '40.00');
    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);

    $this->get(route('storefront.cart'))->assertInertia(fn ($page) => $page
        ->where('cart.free_shipping.progress', 40)
        ->where('cart.free_shipping.remaining', Money::format(6000)));
});

// ── Registration and authentication ─────────────────────────────────────────────────────────────

it('throttles registration and rejects bots through the honeypot', function () {
    $prefix = (string) config('honeypot.name_field_name');
    $this->post(route('register.store'), asBot(registrationPayload(['email' => 'bot@example.com'])));
    $this->post(route('register.store'), collect(registrationPayload(['email' => 'bot2@example.com']))
        ->reject(fn ($v, $k) => str_starts_with($k, $prefix) || $k === config('honeypot.valid_from_field_name'))->all());
    expect(User::query()->whereIn('email', ['bot@example.com', 'bot2@example.com'])->count())->toBe(0);

    for ($i = 0; $i < 3; $i++) {
        $this->post(route('register.store'), registrationPayload(['email' => "user{$i}@example.com", 'password_confirmation' => 'mismatch']));
    }
    $this->post(route('register.store'), registrationPayload(['email' => 'late@example.com']))->assertTooManyRequests();
});

it('refuses login for a deactivated account', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('changes the password only with the correct current password', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $payload = ['password' => 'New-Password-123', 'password_confirmation' => 'New-Password-123'];

    $this->put(route('user-password.update'), [...$payload, 'current_password' => 'wrong'])->assertSessionHasErrorsIn('updatePassword', 'current_password');
    $this->put(route('user-password.update'), [...$payload, 'current_password' => 'password'])->assertSessionHasNoErrors();

    expect(Hash::check('New-Password-123', $user->fresh()->password))->toBeTrue();
});

// ── Admin: users, settings, companies ───────────────────────────────────────────────────────────

it('stops admins from locking themselves out', function () {
    $admin = staffUser('admin');
    $this->actingAs($admin);
    $base = ['name' => $admin->name, 'email' => $admin->email];

    $this->put(route('admin.users.update', $admin), [...$base, 'is_active' => false, 'roles' => ['admin']])->assertSessionHasErrors('is_active');
    $this->put(route('admin.users.update', $admin), [...$base, 'is_active' => true, 'roles' => ['customer']])->assertSessionHasNoErrors();
    expect($admin->fresh()->hasRole('admin'))->toBeTrue()->and($admin->fresh()->is_active)->toBeTrue();

    $this->delete(route('admin.users.destroy', $admin))->assertForbidden();
});

it('keeps the super-admin role when a super-admin edits a super-admin', function () {
    $root = staffUser('super-admin');
    $other = staffUser('super-admin');

    $this->actingAs($root)->put(route('admin.users.update', $other), ['name' => 'Other', 'email' => $other->email, 'is_active' => true, 'roles' => ['admin']])
        ->assertSessionHasNoErrors();

    expect($other->fresh()->hasRole('super-admin'))->toBeTrue()->and($other->fresh()->hasRole('admin'))->toBeTrue();
});

it('saves store settings and formats money in the base currency', function () {
    $this->actingAs(staffUser('admin'));

    $this->put(route('admin.settings.update', 'store'), ['products_per_page' => 24, 'min_order_total' => '12.5', 'guest_checkout' => true])
        ->assertSessionHasNoErrors();

    $settings = app(StoreSettings::class)->refresh();
    expect($settings->min_order_total)->toBe(1250)
        ->and(Money::storeCurrency())->toBe('ILS')
        ->and(Money::format(1250))->toContain('12.50');
});

it('deletes a company payment only through its own company and updates the balance', function () {
    $admin = staffUser('admin');
    [$a, $b] = [opsCompany(), opsCompany()];
    $payment = $a->payments()->create(['amount' => 5000, 'method' => 'cash', 'paid_at' => now(), 'recorded_by' => $admin->id]);
    $this->actingAs($admin);

    $this->delete(route('admin.companies.payments.destroy', [$b, $payment]))->assertNotFound();
    expect($a->balance())->toBe(-5000);

    $this->delete(route('admin.companies.payments.destroy', [$a, $payment]))->assertRedirect();
    expect($a->fresh()->balance())->toBe(0);
});

it('renames a company and deletes a company without history', function () {
    $this->actingAs(staffUser('admin'));
    $company = opsCompany();

    $this->put(route('admin.companies.update', $company), ['name' => $company->name, 'phone' => '0590000000', 'is_active' => true])->assertSessionHasNoErrors();
    $this->delete(route('admin.companies.destroy', $company))->assertRedirect();

    expect(Company::query()->count())->toBe(0);
});

it('hides inactive companies from the POS order screen', function () {
    $rep = User::factory()->create(['is_active' => true])->assignRole('sales-rep');
    $inactive = Company::query()->create(['name' => 'مغلقة', 'is_active' => false]);

    $this->actingAs($rep)->get(route('pos.create', $inactive))->assertNotFound();
});

// ── Storefront pages (smoke) ────────────────────────────────────────────────────────────────────

it('serves every public page', function () {
    $category = Category::query()->create(['is_active' => true, 'ar' => ['name' => 'شوكولاتة']]);
    $product = makeSimpleProduct(overrides: ['category_ids' => [$category->id]]);

    foreach ([
        route('storefront.home'), route('storefront.shop'), route('storefront.shop', ['q' => 'منتج', 'sort' => 'price_asc', 'sale' => 1]),
        route('storefront.category', ['slug' => $category->slug]), route('storefront.brands'),
        route('storefront.contact'), route('storefront.about'), route('storefront.product', ['slug' => $product->slug]),
        route('storefront.cart'), route('login'), route('register'), route('password.request'),
        '/robots.txt', '/sitemap.xml', '/sitemaps/ar-products.xml', '/sitemaps/en-categories.xml', '/sitemaps/he-pages.xml',
    ] as $url) {
        expect($this->get($url)->status())->toBe(200, $url);
    }
});

it('exposes the sale percentage and a matching struck-through price on product cards', function () {
    makeSimpleProduct(price: '50.00', variant: ['sale_price' => 4000]);

    $this->get(route('storefront.shop'))->assertInertia(fn ($page) => $page
        ->where('products.data.0.discount_percent', 20)
        ->where('products.data.0.regular_price', Money::format(5000))
        ->where('products.data.0.price', Money::format(4000)));
});
