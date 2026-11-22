<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\Cart\CalculateCartTotals;
use App\Actions\Cart\ResolveCart;
use App\Actions\Orders\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Http\Resources\Storefront\CartResource;
use App\Models\Order;
use App\Support\Money;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Honeypot\Honeypot;

class CheckoutController extends Controller
{
    public function __construct(private readonly ResolveCart $resolveCart) {}

    public function show(Request $request, CalculateCartTotals $totals): Response|RedirectResponse
    {
        $cart = $this->resolveCart->handle($request, create: false);

        if (! $cart || $cart->items()->doesntExist()) {
            return redirect()->route('storefront.cart');
        }

        return Inertia::render('Storefront/Checkout/Index', [
            'cart' => new CartResource($cart->load(CartResource::RELATIONS), $totals->handle($cart)),
            'honeypot' => app(Honeypot::class)->toArray(),
            'prefill' => $request->user() ? ['customer_name' => $request->user()->name, 'customer_email' => $request->user()->email, 'customer_phone' => $request->user()->phone] : null,
            'seo' => SeoData::make(__('cart.checkout_title'))->noindex(),
        ]);
    }

    public function store(CheckoutRequest $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $cart = $this->resolveCart->handle($request, create: false);
        abort_unless($cart, 404);

        $order = $placeOrder->handle($cart, $request->customer(), $request->user()?->id);

        // Signed, expiring URL: guests can see their confirmation without exposing sequential order data.
        return redirect()->to(URL::temporarySignedRoute('storefront.order.thanks', now()->addHours(2), ['order' => $order->number]));
    }

    public function thanks(string $order): Response
    {
        $order = Order::query()->where('number', $order)->firstOrFail();

        return Inertia::render('Storefront/Checkout/Thanks', [
            'order' => [
                'number' => $order->number,
                'total' => Money::format($order->total, $order->currency),
                'phone' => $order->customer_phone,
            ],
            'seo' => SeoData::make(__('cart.thank_you'))->noindex(),
        ]);
    }
}
