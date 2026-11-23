<?php

namespace App\Actions\Orders;

use App\Actions\Cart\CalculateCartTotals;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\OrderPlaced;
use App\Models\Cart;
use App\Models\Order;
use App\Settings\StoreSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Converts a cart into an order atomically: locks variants, re-validates availability and stock,
 * snapshots names/prices, decrements managed stock, and empties the cart.
 *
 * @phpstan-type CustomerInput array{customer_name:string, customer_email?:string|null, customer_phone:string,
 *   shipping_city?:string|null, shipping_address?:string|null, customer_note?:string|null}
 */
class PlaceOrder
{
    public function __construct(
        private readonly StoreSettings $settings,
        private readonly CalculateCartTotals $totals,
        private readonly BuildOrderLines $buildLines,
    ) {}

    /** @param CustomerInput $customer */
    public function handle(Cart $cart, array $customer, ?int $userId = null, ?int $shippingMethodId = null): Order
    {
        return DB::transaction(function () use ($cart, $customer, $userId, $shippingMethodId): Order {
            $cart->load('items');

            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => __('cart.empty')]);
            }

            $quantities = [];
            foreach ($cart->items as $item) {
                $quantities[$item->product_variant_id] = ($quantities[$item->product_variant_id] ?? 0) + $item->quantity;
            }
            $lines = $this->buildLines->handle($quantities);

            $subtotal = array_sum(array_column($lines, 'line_total'));
            if ($subtotal < $this->settings->min_order_total) {
                throw ValidationException::withMessages(['cart' => __('cart.below_minimum')]);
            }

            // Re-checked here (not only in the request): availability depends on the locked subtotal.
            $method = $this->totals->availableMethods($subtotal)->firstWhere('id', $shippingMethodId);
            if ($method === null) {
                throw ValidationException::withMessages(['shipping_method_id' => __('cart.shipping_unavailable')]);
            }
            $shipping = $method->costFor($subtotal);
            $needsAddress = $method->type->requiresAddress();

            $totals = [
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'shipping_total' => $shipping,
                'total' => $subtotal + $shipping,
            ];

            $order = Order::create([
                ...$customer,
                'shipping_city' => $needsAddress ? ($customer['shipping_city'] ?? null) : null,
                'shipping_address' => $needsAddress ? ($customer['shipping_address'] ?? null) : null,
                'shipping_method_id' => $method->id,
                'shipping_method_name' => $method->name,
                'number' => BuildOrderLines::generateNumber(),
                'user_id' => $userId,
                'status' => OrderStatus::Pending,
                'channel' => OrderChannel::Web,
                'payment_method' => PaymentMethod::CashOnDelivery,
                'currency' => $this->settings->currency,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'shipping_total' => $totals['shipping_total'],
                'total' => $totals['total'],
                'locale' => app()->getLocale(),
                'placed_at' => now(),
            ]);

            $order->items()->createMany($lines);
            $cart->items()->delete();

            OrderPlaced::dispatch($order);

            return $order;
        });
    }
}
