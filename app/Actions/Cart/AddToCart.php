<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class AddToCart
{
    public const MAX_LINE_QUANTITY = 999;

    public function handle(Cart $cart, ProductVariant $variant, int $quantity): CartItem
    {
        $variant->loadMissing('product');

        if (! $variant->product?->isPublished() || ! $variant->isPurchasable()) {
            throw ValidationException::withMessages(['variant_id' => __('cart.unavailable')]);
        }

        $item = $cart->items()->firstOrNew(['product_variant_id' => $variant->id]);
        $newQuantity = min(self::MAX_LINE_QUANTITY, ($item->exists ? $item->quantity : 0) + $quantity);

        $available = $variant->availableQuantity();
        if ($available !== null && $newQuantity > $available) {
            throw ValidationException::withMessages(['quantity' => __('cart.insufficient_stock', ['count' => $available])]);
        }

        $item->quantity = $newQuantity;
        $item->save();
        $cart->touch();

        return $item;
    }
}
