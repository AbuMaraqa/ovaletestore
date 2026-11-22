<?php

namespace App\Actions\Cart;

use App\Models\CartItem;
use Illuminate\Validation\ValidationException;

class UpdateCartItemQuantity
{
    public function handle(CartItem $item, int $quantity): ?CartItem
    {
        if ($quantity <= 0) {
            $item->delete();

            return null;
        }

        $available = $item->variant->availableQuantity();
        if ($available !== null && $quantity > $available) {
            throw ValidationException::withMessages(['quantity' => __('cart.insufficient_stock', ['count' => $available])]);
        }

        $item->update(['quantity' => min($quantity, AddToCart::MAX_LINE_QUANTITY)]);
        $item->cart->touch();

        return $item;
    }
}
