<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\CalculateCartTotals;
use App\Actions\Cart\ResolveCart;
use App\Actions\Cart\UpdateCartItemQuantity;
use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\CartResource;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private readonly ResolveCart $resolveCart) {}

    public function show(Request $request, CalculateCartTotals $totals): Response
    {
        $cart = $this->resolveCart->handle($request, create: false);

        return Inertia::render('Storefront/Cart/Index', [
            'cart' => $cart ? new CartResource($cart->load(CartResource::RELATIONS), $totals->handle($cart)) : null,
            'seo' => SeoData::make(__('cart.title'))->noindex(),
        ]);
    }

    public function store(Request $request, AddToCart $addToCart): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.AddToCart::MAX_LINE_QUANTITY],
        ]);

        $addToCart->handle(
            $this->resolveCart->handle($request),
            ProductVariant::query()->findOrFail($data['variant_id']),
            (int) $data['quantity'],
        );

        return back()->with('success', __('storefront.added_to_cart'));
    }

    public function update(Request $request, CartItem $item, UpdateCartItemQuantity $update): RedirectResponse
    {
        $this->authorizeItem($request, $item);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.AddToCart::MAX_LINE_QUANTITY]]);
        $update->handle($item, (int) $data['quantity']);

        return back();
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $item);
        $item->delete();

        return back();
    }

    /** A cart item can only be modified through the visitor's own cart (prevents IDOR on item ids). */
    private function authorizeItem(Request $request, CartItem $item): void
    {
        $cart = $this->resolveCart->handle($request, create: false);
        abort_unless($cart && $item->cart_id === $cart->id, 404);
    }
}
