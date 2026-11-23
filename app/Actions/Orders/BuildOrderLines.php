<?php

namespace App\Actions\Orders;

use App\Enums\StockStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared by the storefront checkout and the POS. Must run inside the caller's DB transaction:
 * locks the variants, re-validates availability and stock, snapshots name/SKU/price and
 * decrements managed stock.
 *
 * @phpstan-type OrderLine array{product_id:int, product_variant_id:int, name:string, variant_label:?string,
 *   sku:?string, unit_price:int, quantity:int, line_total:int}
 */
class BuildOrderLines
{
    /**
     * @param  array<int, int>  $quantities  variant id => quantity
     * @param  string  $errorKey  validation key errors are reported under
     * @return list<OrderLine>
     */
    public function handle(array $quantities, string $errorKey = 'cart'): array
    {
        if ($quantities === []) {
            throw ValidationException::withMessages([$errorKey => __('cart.empty')]);
        }

        /** @var Collection<int, ProductVariant> $variants */
        $variants = ProductVariant::query()
            ->whereIn('id', array_keys($quantities))
            ->with(['product.translations', 'terms.translations'])
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($quantities as $variantId => $quantity) {
            $variant = $variants->get($variantId);
            $this->assertOrderable($variant, $quantity, $errorKey);

            $unitPrice = $variant->effectivePrice();
            $lines[] = [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'name' => (string) $variant->product?->name, // current locale, with the fallback chain
                'variant_label' => $variant->terms->isNotEmpty() ? $variant->label() : null,
                'sku' => $variant->sku,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $unitPrice * $quantity,
            ];

            $this->decrementStock($variant, $quantity);
        }

        return $lines;
    }

    public static function generateNumber(string $prefix = 'OV'): string
    {
        do {
            $number = $prefix.'-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }

    private function assertOrderable(?ProductVariant $variant, int $quantity, string $errorKey): void
    {
        if (! $variant || ! $variant->product?->isPublished() || ! $variant->isPurchasable()) {
            throw ValidationException::withMessages([$errorKey => __('cart.item_unavailable')]);
        }

        $available = $variant->availableQuantity();
        if ($available !== null && $quantity > $available) {
            throw ValidationException::withMessages([$errorKey => __('cart.insufficient_stock', ['count' => $available])]);
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
}
