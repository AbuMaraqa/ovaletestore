<?php

namespace App\Actions\Orders;

use App\Actions\Cart\CalculateCartTotals;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\OrderPlaced;
use App\Models\Cart;
use App\Models\Currency;
use App\Models\Order;
use App\Settings\StoreSettings;
use App\Support\CurrencyContext;
use App\Support\PriceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Converts a cart into an order atomically: locks variants, re-validates availability and stock,
 * snapshots names/prices, decrements managed stock, and empties the cart.
 *
 * Rules (minimum order, shipping availability, free-shipping thresholds) are checked in the base currency;
 * the order itself is charged in the visitor's currency: each unit price is converted, then multiplied,
 * so the order matches the cart line for line. `base_total` keeps the exact base amount for reports.
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
        private readonly CurrencyContext $currency,
    ) {}

    /**
     * @param  CustomerInput  $customer
     * @param  string|null  $expectedCurrency  the currency the checkout page showed (refused if it changed since)
     */
    public function handle(Cart $cart, array $customer, ?int $userId = null, ?int $shippingMethodId = null, ?string $expectedCurrency = null): Order
    {
        return DB::transaction(function () use ($cart, $customer, $userId, $shippingMethodId, $expectedCurrency): Order {
            $cart->load('items');
            $this->assertCurrencyUnchanged($expectedCurrency);

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

            $baseTotal = $subtotal + $shipping;
            $currency = $this->currency->current();
            $lines = array_map(function (array $line) use ($currency): array {
                $line['unit_price'] = $currency->fromBase($line['unit_price']);
                $line['line_total'] = $line['unit_price'] * $line['quantity'];

                return $line;
            }, $lines);
            $chargedSubtotal = array_sum(array_column($lines, 'line_total'));
            $chargedShipping = $currency->fromBase($shipping);

            $totals = [
                'subtotal' => $chargedSubtotal,
                'discount_total' => 0,
                'shipping_total' => $chargedShipping,
                'total' => $chargedSubtotal + $chargedShipping,
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
                'currency' => $currency->code,
                'exchange_rate' => $currency->is_default ? 1 : $currency->rate,
                'base_total' => $baseTotal,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'shipping_total' => $totals['shipping_total'],
                'total' => $totals['total'],
                'locale' => app()->getLocale(),
                'placed_at' => now(),
                // Trader orders remember the price list they were priced with.
                'price_tier_id' => app(PriceContext::class)->tier()?->id,
            ]);

            $order->items()->createMany($lines);
            $cart->items()->delete();

            OrderPlaced::dispatch($order);

            return $order;
        });
    }

    /**
     * The customer must be charged in the currency, and at the rate, the checkout showed: if the currency was
     * switched in another tab, deactivated, or its rate edited meanwhile, the order is refused and the page
     * reloads with the current prices.
     */
    private function assertCurrencyUnchanged(?string $expected): void
    {
        $currency = $this->currency->current();
        $changed = $expected !== null && strtoupper($expected) !== $currency->code;

        if (! $changed && $currency->exists) {
            $fresh = Currency::query()->find($currency->id);
            $changed = $fresh === null
                || $fresh->is_default !== $currency->is_default
                || (! $fresh->is_active && ! $fresh->is_default)
                || (string) $fresh->rate !== (string) $currency->rate;
        }

        if ($changed) {
            throw ValidationException::withMessages(['cart' => __('currencies.changed_at_checkout')]);
        }
    }
}
