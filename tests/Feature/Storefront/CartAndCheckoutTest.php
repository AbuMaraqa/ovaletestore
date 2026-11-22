<?php

use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function checkoutPayload(): array
{
    return [
        'customer_name' => 'محمد أحمد',
        'customer_phone' => '0599 123 456',
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
        ->assertSessionHasErrors(['customer_name', 'customer_phone', 'shipping_city', 'shipping_address']);

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
