<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\Cart\CalculateCartTotals;
use App\Actions\Cart\ResolveCart;
use App\Actions\Orders\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Http\Resources\Storefront\CartResource;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Settings\StoreSettings;
use App\Support\CurrencyContext;
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

    public function show(Request $request, CalculateCartTotals $totals, StoreSettings $store): Response|RedirectResponse
    {
        if (! $store->guest_checkout && ! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $cart = $this->resolveCart->handle($request, create: false);

        if (! $cart || $cart->items()->doesntExist()) {
            return redirect()->route('storefront.cart');
        }

        $cartTotals = $totals->handle($cart);
        $subtotal = $cartTotals['subtotal'];
        $currency = app(CurrencyContext::class);
        $displaySubtotal = $cartTotals['display']['subtotal'];

        return Inertia::render('Storefront/Checkout/Index', [
            'cart' => new CartResource($cart->load(CartResource::RELATIONS), $cartTotals),
            // Each option carries its own cost and resulting total so the summary updates without a round-trip.
            'shipping_options' => $totals->availableMethods($subtotal)->map(fn (ShippingMethod $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'description' => $m->description,
                'requires_address' => $m->type->requiresAddress(),
                'cost' => $m->costFor($subtotal) > 0 ? $currency->format($m->costFor($subtotal)) : null,
                'total' => $currency->formatConverted($displaySubtotal + $currency->convert($m->costFor($subtotal))),
                'free_over' => $m->free_over && $m->costFor($subtotal) > 0 ? $currency->format($m->free_over) : null,
            ])->all(),
            'honeypot' => app(Honeypot::class)->toArray(),
            // Cash on delivery in a foreign currency: say plainly what will be collected.
            'currency_note' => $currency->isBase() ? null : __('currencies.pay_in', ['currency' => $currency->current()->label(), 'code' => $currency->current()->code]),
            'prefill' => $this->prefill($request),
            'seo' => SeoData::make(__('cart.checkout_title'))->noindex(),
        ]);
    }

    public function store(CheckoutRequest $request, PlaceOrder $placeOrder, StoreSettings $store): RedirectResponse
    {
        abort_if(! $store->guest_checkout && ! $request->user(), 403);

        $cart = $this->resolveCart->handle($request, create: false);
        abort_unless($cart, 404);

        $order = $placeOrder->handle(
            $cart, $request->customer(), $request->user()?->id, (int) $request->validated('shipping_method_id'), $request->validated('currency'),
        );

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

    /**
     * Signed-in customers start from their account and their last delivery address (all fields stay editable).
     *
     * @return array<string, string|null>|null
     */
    private function prefill(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }
        $last = $user->orders()->whereNotNull('shipping_city')->latest('placed_at')->first(['customer_phone', 'shipping_city', 'shipping_address']);

        return [
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone ?: $last?->customer_phone,
            'shipping_city' => $last?->shipping_city,
            'shipping_address' => $last?->shipping_address,
        ];
    }
}
