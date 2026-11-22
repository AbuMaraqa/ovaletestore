<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Settings\StoreSettings;

/**
 * Prices are always recalculated from current variant data — the cart never stores prices.
 *
 * @phpstan-type CartTotals array{subtotal:int, discount_total:int, shipping_total:int, total:int, item_count:int, meets_minimum:bool}
 */
class CalculateCartTotals
{
    public function __construct(private readonly StoreSettings $settings) {}

    /** @return CartTotals */
    public function handle(Cart $cart): array
    {
        $cart->loadMissing('items.variant');

        $subtotal = $cart->items->sum(fn (CartItem $item) => $item->variant->effectivePrice() * $item->quantity);
        $itemCount = (int) $cart->items->sum('quantity');
        $shipping = $itemCount > 0 ? $this->settings->shipping_fee : 0;

        return [
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'shipping_total' => $shipping,
            'total' => $subtotal + $shipping,
            'item_count' => $itemCount,
            'meets_minimum' => $subtotal >= $this->settings->min_order_total,
        ];
    }
}
