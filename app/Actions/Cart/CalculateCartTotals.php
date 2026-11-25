<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ShippingMethod;
use App\Settings\StoreSettings;
use App\Support\CurrencyContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * Prices are always recalculated from current variant data — the cart never stores prices.
 * Shipping uses the chosen method (or the first one available for this subtotal).
 *
 * Amounts are in the base currency; `display` holds the same figures in the visitor's currency, converted
 * per unit price exactly as PlaceOrder will charge them.
 *
 * @phpstan-type CartTotals array{subtotal:int, discount_total:int, shipping_total:int, total:int, item_count:int,
 *   meets_minimum:bool, minimum_missing:int, shipping_method:?ShippingMethod,
 *   display:array{subtotal:int, shipping_total:int, total:int}}
 */
class CalculateCartTotals
{
    public function __construct(private readonly StoreSettings $settings, private readonly CurrencyContext $currency) {}

    /** @return CartTotals */
    public function handle(Cart $cart, ?int $shippingMethodId = null): array
    {
        $cart->loadMissing('items.variant');

        $subtotal = $cart->items->sum(fn (CartItem $item) => $item->variant->effectivePrice() * $item->quantity);
        $itemCount = (int) $cart->items->sum('quantity');

        $method = $itemCount > 0 ? $this->chooseMethod($subtotal, $shippingMethodId) : null;
        $shipping = $method?->costFor($subtotal) ?? 0;
        $displaySubtotal = $cart->items->sum(fn (CartItem $item) => $this->currency->convert($item->variant->effectivePrice()) * $item->quantity);
        $displayShipping = $this->currency->convert($shipping);

        return [
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'shipping_total' => $shipping,
            'total' => $subtotal + $shipping,
            'item_count' => $itemCount,
            'meets_minimum' => $subtotal >= $this->settings->min_order_total,
            'minimum_missing' => max(0, $this->settings->min_order_total - $subtotal),
            'shipping_method' => $method,
            'display' => ['subtotal' => $displaySubtotal, 'shipping_total' => $displayShipping, 'total' => $displaySubtotal + $displayShipping],
        ];
    }

    /** @return Collection<int, ShippingMethod> active methods offered for this subtotal, in display order */
    public function availableMethods(int $subtotal): Collection
    {
        return ShippingMethod::query()->active()->with('translations')->get()
            ->filter(fn (ShippingMethod $m) => $m->isAvailableFor($subtotal))
            ->values();
    }

    private function chooseMethod(int $subtotal, ?int $id): ?ShippingMethod
    {
        $methods = $this->availableMethods($subtotal);

        return ($id ? $methods->firstWhere('id', $id) : null) ?? $methods->first();
    }
}
