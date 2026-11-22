<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Finds (or lazily creates) the visitor's cart.
 * Guests: token stored in the session (never trusted from user input).
 * Authenticated: the user's persistent cart; a guest cart is merged into it on first resolve after login.
 */
class ResolveCart
{
    private const SESSION_KEY = 'cart_token';

    public function handle(Request $request, bool $create = true): ?Cart
    {
        $user = $request->user();
        $guestCart = $this->guestCart($request);

        if ($user instanceof User) {
            $cart = Cart::query()->where('user_id', $user->id)->first();

            if ($guestCart && $guestCart->id !== $cart?->id) {
                $cart = $this->mergeIntoUserCart($guestCart, $cart, $user);
                $request->session()->forget(self::SESSION_KEY);
            }

            return $cart ?? ($create ? Cart::create(['token' => (string) Str::uuid(), 'user_id' => $user->id]) : null);
        }

        if ($guestCart || ! $create) {
            return $guestCart;
        }

        $cart = Cart::create(['token' => (string) Str::uuid()]);
        $request->session()->put(self::SESSION_KEY, $cart->token);

        return $cart;
    }

    private function guestCart(Request $request): ?Cart
    {
        $token = $request->session()->get(self::SESSION_KEY);

        return $token ? Cart::query()->where('token', $token)->whereNull('user_id')->first() : null;
    }

    private function mergeIntoUserCart(Cart $guest, ?Cart $userCart, User $user): Cart
    {
        return DB::transaction(function () use ($guest, $userCart, $user): Cart {
            if (! $userCart) {
                $guest->update(['user_id' => $user->id]);

                return $guest;
            }

            foreach ($guest->items as $item) {
                $existing = $userCart->items()->where('product_variant_id', $item->product_variant_id)->first();
                $existing
                    ? $existing->update(['quantity' => $existing->quantity + $item->quantity])
                    : $userCart->items()->create($item->only(['product_variant_id', 'quantity']));
            }

            $guest->delete();

            return $userCart;
        });
    }
}
