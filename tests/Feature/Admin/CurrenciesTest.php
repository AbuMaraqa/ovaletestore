<?php

use App\Actions\Currencies\SetBaseCurrency;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\PriceTierPrice;
use App\Models\ShippingMethod;
use App\Settings\StoreSettings;
use App\Support\CurrencyContext;
use App\Support\Money;
use Spatie\Permission\Models\Role;

function addCurrency(string $code = 'USD', string $rate = '0.27', array $overrides = []): Currency
{
    $currency = Currency::query()->create(['code' => $code, 'name' => ['ar' => $code], 'rate' => $rate, ...$overrides]);
    app(CurrencyContext::class)->refresh();

    return $currency;
}

it('starts with the store currency as the only, default currency', function () {
    $base = Currency::query()->sole();

    expect($base->code)->toBe('ILS')->and($base->is_default)->toBeTrue()->and((float) $base->rate)->toBe(1.0)
        ->and(Money::storeCurrency())->toBe('ILS');
});

it('shows storefront prices in the currency the visitor picks', function () {
    addCurrency('USD', '0.27');
    $product = makeSimpleProduct('سكر', '10.00');

    $this->get(route('storefront.product', ['slug' => $product->slug]))
        ->assertInertia(fn ($page) => $page->where('site.currency', 'ILS')->has('currencies', 2));

    $this->post(route('storefront.currency'), ['code' => 'USD'])->assertSessionHasNoErrors();
    expect(session(CurrencyContext::SESSION_KEY))->toBe('USD');

    $this->get(route('storefront.product', ['slug' => $product->slug]))
        ->assertInertia(fn ($page) => $page->where('site.currency', 'USD')
            ->where('product.variants.0.price', fn (string $price) => str_contains($price, '2.70')));
});

it('rejects unknown and inactive currencies', function () {
    addCurrency('JOD', '0.19', ['is_active' => false]);

    $this->post(route('storefront.currency'), ['code' => 'JOD'])->assertSessionHasErrors('code');
    $this->post(route('storefront.currency'), ['code' => 'XXX'])->assertSessionHasErrors('code');
    expect(session(CurrencyContext::SESSION_KEY))->toBeNull();
});

it('charges the order in the chosen currency and keeps the base total for reports', function () {
    addCurrency('USD', '0.27');
    $variant = makeSimpleProduct('طحين', '10.00')->defaultVariant;

    $this->post(route('storefront.currency'), ['code' => 'USD']);
    $this->post(route('storefront.cart.store'), ['variant_id' => $variant->id, 'quantity' => 3]);
    $this->post(route('storefront.checkout.store'), checkoutPayload())->assertSessionHasNoErrors();

    $order = Order::query()->latest('id')->firstOrFail();
    expect($order->currency)->toBe('USD')
        ->and((float) $order->exchange_rate)->toBe(0.27)
        ->and($order->items()->firstOrFail()->unit_price)->toBe(270)     // 10.00 ILS → 2.70 USD
        ->and($order->subtotal)->toBe(810);                               // 3 × 2.70

    $shippingBase = ShippingMethod::query()->findOrFail($order->shipping_method_id)->costFor(3000);
    expect($order->shipping_total)->toBe((int) round($shippingBase * 0.27))
        ->and($order->total)->toBe(810 + $order->shipping_total)
        ->and($order->base_total)->toBe(3000 + $shippingBase);           // exact base amount, not re-derived
});

it('derives the base total for orders placed in the base currency', function () {
    $order = placeWebOrder(2, '15.00');

    expect($order->currency)->toBe('ILS')->and($order->base_total)->toBe($order->total);
});

it('lets only permitted staff manage currencies and protects the default one', function () {
    $this->actingAs(staffUser('product-manager'))->get(route('admin.currencies.index'))->assertForbidden();

    $this->actingAs(staffUser('admin'));
    $this->get(route('admin.currencies.index'))->assertOk();
    // Owners type "1 USD = 3.70 ₪"; the stored rate is its inverse (USD per 1 ILS).
    $this->post(route('admin.currencies.store'), ['code' => 'usd', 'name' => ['ar' => 'دولار'], 'value' => '4', 'is_active' => true])
        ->assertSessionHasNoErrors();
    expect((float) Currency::query()->where('code', 'USD')->sole()->rate)->toBe(0.25);

    $this->post(route('admin.currencies.store'), ['code' => 'USD', 'name' => ['ar' => 'x'], 'value' => '1'])->assertSessionHasErrors('code');
    $this->post(route('admin.currencies.store'), ['code' => 'EUR', 'name' => ['ar' => 'يورو'], 'value' => '0'])->assertSessionHasErrors('value');

    $base = Currency::query()->where('is_default', true)->sole();
    $this->put(route('admin.currencies.update', $base), ['code' => 'EUR', 'name' => ['ar' => 'شيكل'], 'value' => '5', 'is_active' => false])->assertSessionHasNoErrors();
    $base->refresh();
    expect($base->code)->toBe('ILS')->and((float) $base->rate)->toBe(1.0)->and($base->is_active)->toBeTrue();

    $this->delete(route('admin.currencies.destroy', $base))->assertSessionHas('error');
    expect($base->fresh())->not->toBeNull();
});

it('converts every stored amount when the default currency changes', function () {
    $usd = addCurrency('USD', '0.25');
    $jod = addCurrency('JOD', '0.20');
    $variant = makeSimpleProduct('زبدة', '40.00', ['sale_price' => 3600])->defaultVariant;
    $tier = PriceTier::query()->where('code', 'wholesale')->firstOrFail();
    PriceTierPrice::query()->create(['price_tier_id' => $tier->id, 'product_variant_id' => $variant->id, 'price' => 3200]);
    $method = ShippingMethod::query()->firstOrFail();
    $method->update(['cost' => 2000, 'free_over' => 40000]);
    $settings = app(StoreSettings::class);
    $settings->min_order_total = 10000;
    $settings->save();
    $company = Company::query()->create(['name' => 'شركة', 'is_active' => true]);
    $company->payments()->create(['amount' => 8000, 'method' => 'cash', 'paid_at' => now()]);
    $order = placeWebOrder(1, '100.00');

    app(SetBaseCurrency::class)->handle($usd);

    expect($variant->fresh()->regular_price)->toBe(1000)          // 40.00 ILS × 0.25
        ->and($variant->fresh()->sale_price)->toBe(900)
        ->and(PriceTierPrice::query()->sole()->price)->toBe(800)
        ->and($method->fresh()->cost)->toBe(500)
        ->and($method->fresh()->free_over)->toBe(10000)
        ->and(app(StoreSettings::class)->refresh()->min_order_total)->toBe(2500)
        ->and($company->payments()->sole()->amount)->toBe(2000)
        ->and($order->fresh()->base_total)->toBe((int) round($order->total * 0.25))
        ->and($order->fresh()->currency)->toBe('ILS');               // what the customer paid stays as it was

    expect($usd->fresh()->is_default)->toBeTrue()->and((float) $usd->fresh()->rate)->toBe(1.0)
        ->and((float) Currency::query()->where('code', 'ILS')->sole()->rate)->toBe(4.0)
        ->and((float) $jod->fresh()->rate)->toBe(0.8)
        ->and(Money::storeCurrency())->toBe('USD');
});

it('requires its own permission to change the default currency', function () {
    $usd = addCurrency('USD', '0.25');
    $role = Role::findByName('product-manager');
    $role->givePermissionTo('currencies.view', 'currencies.update');

    $this->actingAs(staffUser('product-manager'))->patch(route('admin.currencies.default', $usd))->assertForbidden();
    $this->actingAs(staffUser('admin'))->patch(route('admin.currencies.default', $usd))->assertSessionHasNoErrors();

    expect($usd->fresh()->is_default)->toBeTrue();
});
