<?php

use App\Enums\TraderStatus;
use App\Mail\TraderApprovedMail;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\PriceTierPrice;
use App\Models\User;
use App\Notifications\TraderRequested;
use App\Support\PriceContext;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

function makeTrader(PriceTier $tier, TraderStatus $status = TraderStatus::Approved): User
{
    $user = User::factory()->create(['is_active' => true])->assignRole('customer');
    $user->forceFill(['trader_status' => $status, 'price_tier_id' => $tier->id, 'business_name' => 'محل الأمل'])->save();

    return $user;
}

function wholesaleTier(float $discount = 10): PriceTier
{
    $tier = PriceTier::query()->where('code', 'wholesale')->firstOrFail();
    $tier->update(['discount_percent' => $discount]);

    return $tier;
}

it('prices a trader with the exact tier price, else the automatic discount, never above retail', function () {
    $tier = wholesaleTier(10);
    $exact = makeSimpleProduct('سكر', '50.00')->defaultVariant;
    $auto = makeSimpleProduct('طحين', '20.00')->defaultVariant;
    $sale = makeSimpleProduct('كاكاو', '30.00', ['sale_price' => 2000])->defaultVariant;
    PriceTierPrice::query()->create(['price_tier_id' => $tier->id, 'product_variant_id' => $exact->id, 'price' => 4200]);

    $context = app(PriceContext::class)->useTier($tier);
    expect($exact->effectivePrice())->toBe(4200)          // exact tier price
        ->and($auto->effectivePrice())->toBe(1800)        // 20.00 − 10%
        ->and($sale->effectivePrice())->toBe(2000)        // running sale (20.00) is cheaper than 27.00
        ->and($exact->hasTradePrice())->toBeTrue();

    $context->useTier(null);
    expect($exact->effectivePrice())->toBe(5000);
});

it('gives retail prices to pending traders and to traders on an inactive tier', function () {
    $tier = wholesaleTier(10);
    $pending = makeTrader($tier, TraderStatus::Pending);
    $approved = makeTrader($tier);

    expect($pending->activePriceTier())->toBeNull()->and($approved->activePriceTier()?->id)->toBe($tier->id);
    $tier->update(['is_active' => false]);
    expect($approved->fresh()->activePriceTier())->toBeNull();
});

it('charges an approved trader the trader price at checkout and remembers the tier', function () {
    $tier = wholesaleTier(25);
    $variant = makeSimpleProduct('زبدة', '40.00')->defaultVariant;
    $trader = makeTrader($tier);

    $this->actingAs($trader)->get(route('storefront.product', ['slug' => $variant->product->slug]))
        ->assertInertia(fn ($page) => $page->where('product.variants.0.trade_price', true)->where('product.variants.0.regular_price', fn ($v) => $v !== null));

    $this->actingAs($trader)->post(route('storefront.cart.store'), ['variant_id' => $variant->id, 'quantity' => 2]);
    $this->actingAs($trader)->post(route('storefront.checkout.store'), checkoutPayload())->assertSessionHasNoErrors();

    $order = Order::query()->latest('id')->firstOrFail();
    expect($order->price_tier_id)->toBe($tier->id)
        ->and($order->subtotal)->toBe(6000) // 2 × (40.00 − 25%)
        ->and($order->items()->firstOrFail()->unit_price)->toBe(3000);
});

it('lets a customer request a trader account at sign-up and notifies the reviewers', function () {
    Notification::fake();
    $admin = staffUser('admin');

    $this->post(route('register.store'), registrationPayload(['trader_request' => '1', 'business_name' => 'حلويات النجمة']))->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'sara@example.com')->sole();
    expect($user->trader_status)->toBe(TraderStatus::Pending)->and($user->business_name)->toBe('حلويات النجمة');
    Notification::assertSentTo($admin, TraderRequested::class);

    auth()->logout();
    $this->post(route('register.store'), registrationPayload(['email' => 'b@example.com', 'trader_request' => '1']))->assertSessionHasErrors('business_name');
});

it('lets a signed-in customer request trader prices from their account', function () {
    Notification::fake();
    $customer = User::factory()->create()->assignRole('customer');

    $this->actingAs($customer)->post(route('account.trader.request'), ['business_name' => 'مخبز السلام'])->assertSessionHasNoErrors()->assertRedirect();
    expect($customer->fresh()->trader_status)->toBe(TraderStatus::Pending);
});

it('approves a trader on a tier with the right permission and emails them', function () {
    Mail::fake();
    $tier = wholesaleTier();
    $trader = makeTrader($tier, TraderStatus::Pending);

    $this->actingAs(staffUser('order-manager'))->patch(route('admin.traders.review', $trader), ['status' => 'approved', 'price_tier_id' => $tier->id])->assertForbidden();
    $this->actingAs(staffUser('admin'))->patch(route('admin.traders.review', $trader), ['status' => 'approved', 'price_tier_id' => $tier->id])->assertSessionHasNoErrors();

    expect($trader->fresh()->trader_status)->toBe(TraderStatus::Approved)->and($trader->fresh()->price_tier_id)->toBe($tier->id);
    Mail::assertQueued(TraderApprovedMail::class, fn ($m) => $m->hasTo($trader->email));

    // Staff accounts can never be turned into traders.
    $this->actingAs(staffUser('admin'))->patch(route('admin.traders.review', staffUser('order-manager')), ['status' => 'approved', 'price_tier_id' => $tier->id])->assertNotFound();
});

it('saves exact tier prices from the product form and removes emptied ones', function () {
    $tier = wholesaleTier();
    $product = makeSimpleProduct('شوكولاتة', '10.00');
    $variant = $product->defaultVariant;
    $payload = fn (?string $price) => [
        'type' => 'simple', 'status' => 'published',
        'translations' => ['ar' => ['name' => 'شوكولاتة']],
        'variants' => [['regular_price' => '10.00', 'stock_status' => 'in_stock', 'is_default' => true, 'tier_prices' => [$tier->id => $price]]],
    ];

    $this->actingAs(staffUser('admin'))->put(route('admin.products.update', $product), $payload('8.50'))->assertSessionHasNoErrors();
    expect(PriceTierPrice::query()->where('product_variant_id', $variant->id)->value('price'))->toBe(850);

    $this->actingAs(staffUser('admin'))->put(route('admin.products.update', $product), $payload(null))->assertSessionHasNoErrors();
    expect(PriceTierPrice::query()->where('product_variant_id', $variant->id)->exists())->toBeFalse();
});

it('will not delete a price tier that traders still use', function () {
    $tier = wholesaleTier();
    makeTrader($tier);

    $this->actingAs(staffUser('admin'))->delete(route('admin.price-tiers.destroy', $tier))->assertSessionHas('error');
    expect(PriceTier::query()->whereKey($tier->id)->exists())->toBeTrue();
});

it('lists traders with pending requests first for reviewers', function () {
    $tier = wholesaleTier();
    makeTrader($tier, TraderStatus::Pending);
    makeTrader($tier);

    $this->actingAs(staffUser('admin'))->get(route('admin.traders.index', ['filter' => ['status' => 'pending']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Traders/Index')->has('traders.data', 1)->where('pending', 1));
});
