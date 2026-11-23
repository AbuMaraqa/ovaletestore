<?php

use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\ShippingMethod;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function checkoutPayload(): array
{
    return [
        'customer_name' => 'محمد أحمد',
        'customer_phone' => '0599 123 456',
        'shipping_method_id' => ShippingMethod::query()->where('code', 'west_bank')->value('id'),
        'shipping_city' => 'رام الله',
        'shipping_address' => 'شارع الإرسال 12',
    ];
}

it('adds a product to a guest cart and calculates totals from current prices', function () {
    $product = makeSimpleProduct(price: '10.00');
    $variant = $product->defaultVariant;

    $this->post(route('storefront.cart.store'), ['variant_id' => $variant->id, 'quantity' => 2])->assertRedirect();
    $this->post(route('storefront.cart.store'), ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();

    expect(CartItem::query()->sole()->quantity)->toBe(3);

    $variant->update(['regular_price' => 1200]); // price change is reflected — carts never store prices

    $this->get(route('storefront.cart'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Storefront/Cart/Index')->where('cart.item_count', 3)->where('cart.items.0.quantity', 3));
});

it('refuses out-of-stock variants and quantities above managed stock', function () {
    $soldOut = makeSimpleProduct(variant: ['stock_status' => 'out_of_stock']);
    $limited = makeSimpleProduct(variant: ['manage_stock' => true, 'stock_quantity' => 2]);

    $this->post(route('storefront.cart.store'), ['variant_id' => $soldOut->defaultVariant->id, 'quantity' => 1])->assertSessionHasErrors('variant_id');
    $this->post(route('storefront.cart.store'), ['variant_id' => $limited->defaultVariant->id, 'quantity' => 3])->assertSessionHasErrors('quantity');
});

it('does not let a visitor modify another cart item (IDOR)', function () {
    $product = makeSimpleProduct();
    $foreignCart = Cart::query()->create(['token' => (string) Str::uuid()]);
    $foreignItem = $foreignCart->items()->create(['product_variant_id' => $product->defaultVariant->id, 'quantity' => 1]);

    $this->patch(route('storefront.cart.update', $foreignItem), ['quantity' => 50])->assertNotFound();
    $this->delete(route('storefront.cart.destroy', $foreignItem))->assertNotFound();

    expect($foreignItem->fresh()->quantity)->toBe(1);
});

it('places an order with price snapshots, decrements stock and empties the cart', function () {
    $product = makeSimpleProduct(name: 'كريمة', price: '20.00', variant: ['manage_stock' => true, 'stock_quantity' => 5, 'sku' => 'CR-5L']);

    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 2]);
    $response = $this->post(route('storefront.checkout.store'), checkoutPayload());

    $order = Order::query()->with('items')->sole();
    $response->assertRedirect();
    expect($order->total)->toBe(4000)
        ->and($order->customer_phone)->toBe('0599123456')
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->items->first()->name)->toBe('كريمة')
        ->and($order->items->first()->sku)->toBe('CR-5L')
        ->and($order->items->first()->unit_price)->toBe(2000)
        ->and($product->defaultVariant->fresh()->stock_quantity)->toBe(3)
        ->and(CartItem::query()->count())->toBe(0);

    // Snapshot survives later catalog price changes.
    $product->defaultVariant->update(['regular_price' => 9999]);
    expect($order->items->first()->fresh()->unit_price)->toBe(2000);
});

it('validates checkout input', function () {
    $product = makeSimpleProduct();
    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);

    $this->post(route('storefront.checkout.store'), ['customer_name' => '', 'customer_phone' => 'abc'])
        ->assertSessionHasErrors(['customer_name', 'customer_phone', 'shipping_method_id', 'shipping_city', 'shipping_address']);

    expect(Order::query()->count())->toBe(0);
});

it('enforces order status transitions and restocks on cancellation', function () {
    $product = makeSimpleProduct(variant: ['manage_stock' => true, 'stock_quantity' => 4]);
    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 3]);
    $this->post(route('storefront.checkout.store'), checkoutPayload());
    $order = Order::query()->sole();
    $admin = staffUser('order-manager');

    expect(fn () => app(ChangeOrderStatus::class)->handle($order, OrderStatus::Completed, $admin))->toThrow(ValidationException::class);

    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Cancelled, $admin, 'customer asked');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($product->defaultVariant->fresh()->stock_quantity)->toBe(4)
        ->and($order->notes()->count())->toBe(1);
});

it('charges the chosen shipping method, free above the threshold, and snapshots its name', function () {
    $delivery = ShippingMethod::query()->where('code', 'west_bank')->sole();
    $delivery->update(['cost' => 2500, 'free_over' => 10000]);
    $product = makeSimpleProduct(price: '40.00');

    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 2]);
    $this->post(route('storefront.checkout.store'), checkoutPayload());
    $order = Order::query()->sole();

    expect($order->shipping_total)->toBe(2500)
        ->and($order->total)->toBe(8000 + 2500)
        ->and($order->shipping_method_id)->toBe($delivery->id)
        ->and($order->shipping_method_name)->toBe('توصيل للضفة الغربية');

    // 3 × 40 = 120 ≥ 100 → free.
    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 3]);
    $this->post(route('storefront.checkout.store'), checkoutPayload());

    expect(Order::query()->latest('id')->first()->shipping_total)->toBe(0);
});

it('allows store pickup without an address', function () {
    $pickup = ShippingMethod::query()->where('type', 'pickup')->sole();
    $product = makeSimpleProduct(price: '10.00');
    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);

    $this->post(route('storefront.checkout.store'), [
        'customer_name' => 'سارة', 'customer_phone' => '0599123456', 'shipping_method_id' => $pickup->id,
    ])->assertSessionHasNoErrors();

    $order = Order::query()->sole();
    expect($order->shipping_method_name)->toBe('استلام من المحل')
        ->and($order->shipping_city)->toBeNull()
        ->and($order->shipping_total)->toBe(0);
});

it('rejects inactive methods and methods below their minimum order', function () {
    $delivery = ShippingMethod::query()->where('code', 'west_bank')->sole();
    $product = makeSimpleProduct(price: '10.00');
    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);

    $delivery->update(['min_order' => 5000]);
    $this->post(route('storefront.checkout.store'), checkoutPayload())->assertSessionHasErrors('shipping_method_id');

    $delivery->update(['min_order' => null, 'is_active' => false]);
    $this->post(route('storefront.checkout.store'), checkoutPayload())->assertSessionHasErrors('shipping_method_id');

    expect(Order::query()->count())->toBe(0);
});

it('lets settings managers manage shipping methods and blocks others', function () {
    $this->actingAs(staffUser('order-manager'))->get(route('admin.shipping-methods.index'))->assertForbidden();

    $this->actingAs(staffUser('admin'))->post(route('admin.shipping-methods.store'), [
        'type' => 'delivery', 'cost' => '35', 'free_over' => '300', 'is_active' => true,
        'translations' => ['ar' => ['name' => 'توصيل سريع', 'description' => ''], 'en' => ['name' => 'Express', 'description' => ''], 'he' => ['name' => '', 'description' => '']],
    ])->assertRedirect(route('admin.shipping-methods.index'));

    $method = ShippingMethod::query()->latest('id')->first();
    expect($method->cost)->toBe(3500)->and($method->free_over)->toBe(30000)->and($method->translate('en')->name)->toBe('Express');
});

it('renders the shipping options at checkout and the admin shipping pages', function () {
    $product = makeSimpleProduct(price: '10.00');
    $this->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);

    $this->get(route('storefront.checkout'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Storefront/Checkout/Index')
        ->has('shipping_options', 4)
        ->where('shipping_options.0.name', 'توصيل للداخل')
        ->where('shipping_options.3.requires_address', false));

    $method = ShippingMethod::query()->where('code', 'jerusalem')->sole();
    $this->actingAs(staffUser('admin'));
    $this->get(route('admin.shipping-methods.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Admin/ShippingMethods/Index')->has('methods', 4));
    $this->get(route('admin.shipping-methods.edit', $method))->assertOk()->assertInertia(fn ($page) => $page->component('Admin/ShippingMethods/Form')->where('method.translations.ar.name', 'توصيل للقدس'));
});
