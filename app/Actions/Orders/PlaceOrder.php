<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\StockStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Settings\StoreSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Converts a cart into an order atomically: locks variants, re-validates availability and stock,
 * snapshots names/prices, decrements managed stock, and empties the cart.
 *
 * @phpstan-type CustomerInput array{customer_name:string, customer_email?:string|null, customer_phone:string,
 *   shipping_city:string, shipping_address:string, customer_note?:string|null}
 */
class PlaceOrder
{
    public function __construct(private readonly StoreSettings $settings) {}

    /** @param CustomerInput $customer */
    public function handle(Cart $cart, array $customer, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($cart, $customer, $userId): Order {
            $cart->load('items');

            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => __('cart.empty')]);
            }

            /** @var Collection<int, ProductVariant> $variants */
            $variants = ProductVariant::query()
                ->whereIn('id', $cart->items->pluck('product_variant_id'))
                ->with(['product.translations', 'terms.translations'])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = [];
            foreach ($cart->items as $item) {
                $variant = $variants->get($item->product_variant_id);
                $this->assertOrderable($variant, $item->quantity);

                $unitPrice = $variant->effectivePrice();
                $lines[] = [
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'name' => $variant->product->translate(app()->getLocale())?->name ?? (string) $variant->product->name,
                    'variant_label' => $variant->terms->isNotEmpty() ? $variant->label() : null,
                    'sku' => $variant->sku,
                    'unit_price' => $unitPrice,
                    'quantity' => $item->quantity,
                    'line_total' => $unitPrice * $item->quantity,
                ];

                $this->decrementStock($variant, $item->quantity);
            }

            $totals = [
                'subtotal' => $subtotal = array_sum(array_column($lines, 'line_total')),
                'discount_total' => 0,
                'shipping_total' => $this->settings->shipping_fee,
                'total' => $subtotal + $this->settings->shipping_fee,
                'meets_minimum' => $subtotal >= $this->settings->min_order_total,
            ];

            if (! $totals['meets_minimum']) {
                throw ValidationException::withMessages(['cart' => __('cart.below_minimum')]);
            }

            $order = Order::create([
                ...$customer,
                'number' => $this->generateNumber(),
                'user_id' => $userId,
                'status' => OrderStatus::Pending,
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

            return $order;
        });
    }

    private function assertOrderable(?ProductVariant $variant, int $quantity): void
    {
        if (! $variant || ! $variant->product?->isPublished() || ! $variant->isPurchasable()) {
            throw ValidationException::withMessages(['cart' => __('cart.item_unavailable')]);
        }

        $available = $variant->availableQuantity();
        if ($available !== null && $quantity > $available) {
            throw ValidationException::withMessages(['cart' => __('cart.insufficient_stock', ['count' => $available])]);
        }
    }

    private function decrementStock(ProductVariant $variant, int $quantity): void
    {
        if (! $variant->manage_stock) {
            return;
        }

        $variant->stock_quantity = (int) $variant->stock_quantity - $quantity;
        if ($variant->stock_quantity <= 0 && $variant->stock_status === StockStatus::InStock) {
            $variant->stock_status = StockStatus::OutOfStock;
        }
        $variant->save();
    }

    private function generateNumber(): string
    {
        do {
            $number = 'OV-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
