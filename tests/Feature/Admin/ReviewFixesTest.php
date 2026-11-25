<?php

use App\Actions\Currencies\SetBaseCurrency;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TraderStatus;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\User;
use App\Support\CurrencyContext;
use App\Support\PriceContext;

/** Regression tests for the 2026-11-25 engineering review (see documentation/review-2026-11-25.md). */
function reviewCurrency(string $code, string $rate): Currency
{
    $currency = Currency::query()->create(['code' => $code, 'name' => ['ar' => $code], 'rate' => $rate, 'is_active' => true]);
    app(CurrencyContext::class)->refresh();

    return $currency;
}

it('converts prices only once when "make default" is sent twice', function () {
    $usd = reviewCurrency('USD', '0.25');
    $variant = makeSimpleProduct('سكر', '40.00')->defaultVariant;

    $stale = Currency::query()->findOrFail($usd->id); // the second request still holds the pre-switch row
    app(SetBaseCurrency::class)->handle($usd);
    app(SetBaseCurrency::class)->handle($stale);

    expect($variant->fresh()->regular_price)->toBe(1000)
        ->and((float) Currency::query()->where('code', 'ILS')->sole()->rate)->toBe(4.0);
});

it('re-expresses past orders against the new base currency', function () {
    $usd = reviewCurrency('USD', '0.25');
    $ils = placeWebOrder(1, '100.00');            // paid in ILS, rate 1
    $this->post(route('storefront.currency'), ['code' => 'USD']);
    $paidInUsd = placeWebOrder(1, '100.00');       // paid in USD at 0.25

    app(SetBaseCurrency::class)->handle($usd);

    $ils->refresh();
    $paidInUsd->refresh();
    expect((float) $ils->exchange_rate)->toBe(4.0)                         // ILS per 1 USD
        ->and($ils->base_total)->toBe((int) round($ils->total * 0.25))
        ->and((float) $paidInUsd->exchange_rate)->toBe(1.0)
        ->and($paidInUsd->base_total)->toBe($paidInUsd->total);              // exact, not re-rounded
});

it('refuses an order when the currency changed after the checkout page was shown', function () {
    reviewCurrency('USD', '0.27');
    $variant = makeSimpleProduct('طحين', '10.00')->defaultVariant;
    $this->post(route('storefront.cart.store'), ['variant_id' => $variant->id, 'quantity' => 1]);

    // The page showed ILS, then the visitor switched to USD in another tab.
    $this->post(route('storefront.currency'), ['code' => 'USD']);
    $this->post(route('storefront.checkout.store'), [...checkoutPayload(), 'currency' => 'ILS'])->assertSessionHasErrors('cart');
    expect(Order::query()->count())->toBe(0);

    $this->post(route('storefront.checkout.store'), [...checkoutPayload(), 'currency' => 'USD'])->assertSessionHasNoErrors();
    expect(Order::query()->sole()->currency)->toBe('USD');
});

it('keeps users.* permissions away from customer accounts', function () {
    $customer = User::factory()->create()->assignRole('customer');
    $this->actingAs(staffUser('admin'));

    $this->get(route('admin.users.edit', $customer))->assertForbidden();
    $this->put(route('admin.users.update', $customer), ['name' => 'x', 'email' => 'taken@example.com', 'is_active' => true, 'roles' => []])->assertForbidden();
    expect($customer->fresh()->email)->not->toBe('taken@example.com');
});

it('gives a trader who was later made staff the retail price', function () {
    $tier = PriceTier::query()->where('code', 'wholesale')->firstOrFail();
    $tier->update(['discount_percent' => 20]);
    $user = User::factory()->create(['is_active' => true])->assignRole('customer');
    $user->forceFill(['trader_status' => TraderStatus::Approved, 'price_tier_id' => $tier->id])->save();

    expect($user->activePriceTier()?->id)->toBe($tier->id);
    $user->assignRole('sales-rep');
    expect($user->fresh()->activePriceTier())->toBeNull();

    $variant = makeSimpleProduct('زبدة', '50.00')->defaultVariant;
    $this->actingAs($user->fresh());
    app(PriceContext::class)->useTier($user->fresh()->activePriceTier());
    expect($variant->effectivePrice())->toBe(5000);
});

it('only deletes a company on-account order after it was cancelled', function () {
    $company = Company::query()->create(['name' => 'شركة الاختبار']);
    $order = Order::query()->create([
        'number' => 'POS-TEST-1', 'company_id' => $company->id, 'status' => OrderStatus::Shipped, 'channel' => OrderChannel::Pos,
        'payment_method' => PaymentMethod::OnAccount, 'customer_name' => $company->name, 'currency' => 'ILS',
        'subtotal' => 5000, 'total' => 5000, 'locale' => 'ar', 'placed_at' => now(),
    ]);
    $this->actingAs(staffUser('admin'));

    $this->delete(route('admin.orders.destroy', $order))->assertForbidden();
    expect($company->balance())->toBe(5000);

    $order->forceFill(['status' => OrderStatus::Cancelled])->save();
    $this->delete(route('admin.orders.destroy', $order))->assertRedirect();
    expect(Order::query()->find($order->id))->toBeNull();
});

it('ignores trader prices sent by someone without the price-tier permission', function () {
    $product = makeSimpleProduct('كاكاو', '30.00');
    $tier = PriceTier::query()->where('code', 'wholesale')->firstOrFail();
    $this->actingAs(staffUser('content-manager'));

    $edit = $this->get(route('admin.products.edit', $product))->assertOk();
    $payload = $edit->viewData('page')['props']['product'];
    $payload['variants'][0]['tier_prices'] = [$tier->id => '1.00'];

    $this->put(route('admin.products.update', $product), $payload);
    expect($product->defaultVariant->tierPrices()->count())->toBe(0);
});
