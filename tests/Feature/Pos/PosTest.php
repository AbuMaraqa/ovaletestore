<?php

use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewOrderPlaced;
use Illuminate\Support\Facades\Notification;

function salesRep(): User
{
    return User::factory()->create(['is_active' => true])->assignRole('sales-rep');
}

function makeCompany(array $attributes = []): Company
{
    return Company::query()->create(array_merge(['name' => 'مخبز النور '.uniqid(), 'phone' => '0599000000', 'city' => 'نابلس'], $attributes));
}

it('lets only sales reps (and admins) into the POS, and sends reps there after login', function () {
    $this->get(route('pos.index'))->assertRedirect();
    $this->actingAs(User::factory()->create()->assignRole('customer'))->get(route('pos.index'))->assertForbidden();
    $this->actingAs(User::factory()->create(['is_active' => false])->assignRole('sales-rep'))->get(route('pos.index'))->assertRedirect(route('login'));
    $this->assertGuest();

    $rep = salesRep();
    auth()->logout();
    $this->post(route('login'), ['email' => $rep->email, 'password' => 'password'])->assertRedirect(route('pos.index'));

    makeCompany(['name' => 'شركة فعالة']);
    makeCompany(['name' => 'شركة موقوفة', 'is_active' => false]);

    $this->actingAs($rep)->get(route('pos.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Pos/Index')->has('companies', 1)->where('companies.0.name', 'شركة فعالة'));

    // A rep has no admin panel.
    $this->actingAs($rep)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs(staffUser('admin'))->get(route('pos.index'))->assertOk();
});

it('shows the whole catalog with website prices on the order screen', function () {
    $product = makeSimpleProduct(name: 'كريمة', price: '20.00');
    makeSimpleProduct(variant: ['stock_status' => 'out_of_stock']);
    $company = makeCompany();

    $this->actingAs(salesRep())->get(route('pos.create', $company))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Pos/Order')
        ->where('company.id', $company->id)
        ->has('catalog.items', 2)
        ->where('catalog.items.0.id', $product->defaultVariant->id)
        ->where('catalog.items.0.price', 2000)
        ->where('catalog.items.1.available', false));
});

it('places an on-account order for a company with website prices, stock and a staff notification', function () {
    Notification::fake();
    $staff = staffUser('admin');
    $rep = salesRep();
    $company = makeCompany(['name' => 'حلويات السعادة', 'city' => 'رام الله']);
    $a = makeSimpleProduct(name: 'شوكولاتة', price: '35.50', variant: ['manage_stock' => true, 'stock_quantity' => 10, 'sku' => 'CH-1']);
    $b = makeSimpleProduct(name: 'حشوة', price: '12.00');

    $this->actingAs($rep)->post(route('pos.orders.store'), [
        'company_id' => $company->id,
        'items' => [['variant_id' => $a->defaultVariant->id, 'quantity' => 4], ['variant_id' => $b->defaultVariant->id, 'quantity' => 1]],
        'note' => 'التوصيل صباحاً',
    ])->assertRedirect();

    $order = Order::query()->with('items')->sole();
    expect($order->channel)->toBe(OrderChannel::Pos)
        ->and($order->payment_method)->toBe(PaymentMethod::OnAccount)
        ->and($order->company_id)->toBe($company->id)
        ->and($order->sales_rep_id)->toBe($rep->id)
        ->and($order->customer_name)->toBe('حلويات السعادة')
        ->and($order->shipping_city)->toBe('رام الله')
        ->and($order->shipping_total)->toBe(0)
        ->and($order->total)->toBe(4 * 3550 + 1200)
        ->and($order->number)->toStartWith('POS-')
        ->and($order->customer_note)->toBe('التوصيل صباحاً')
        ->and($order->items)->toHaveCount(2)
        ->and($a->defaultVariant->fresh()->stock_quantity)->toBe(6)
        ->and($company->balance())->toBe(15400);

    Notification::assertSentTo($staff, NewOrderPlaced::class);
});

it('rejects unavailable items, excess stock and inactive companies', function () {
    $rep = salesRep();
    $company = makeCompany();
    $limited = makeSimpleProduct(variant: ['manage_stock' => true, 'stock_quantity' => 2]);
    $soldOut = makeSimpleProduct(variant: ['stock_status' => 'out_of_stock']);

    $this->actingAs($rep)->post(route('pos.orders.store'), ['company_id' => $company->id, 'items' => [['variant_id' => $limited->defaultVariant->id, 'quantity' => 3]]])
        ->assertSessionHasErrors('items');
    $this->post(route('pos.orders.store'), ['company_id' => $company->id, 'items' => [['variant_id' => $soldOut->defaultVariant->id, 'quantity' => 1]]])
        ->assertSessionHasErrors('items');
    $this->post(route('pos.orders.store'), ['company_id' => $company->id, 'items' => []])->assertSessionHasErrors('items');

    $company->update(['is_active' => false]);
    $this->post(route('pos.orders.store'), ['company_id' => $company->id, 'items' => [['variant_id' => $limited->defaultVariant->id, 'quantity' => 1]]])
        ->assertSessionHasErrors('company_id');

    expect(Order::query()->count())->toBe(0)->and($limited->defaultVariant->fresh()->stock_quantity)->toBe(2);
});

it('shows a rep only their own orders', function () {
    $alice = salesRep();
    $bob = salesRep();
    $company = makeCompany();
    $product = makeSimpleProduct();

    $this->actingAs($alice)->post(route('pos.orders.store'), ['company_id' => $company->id, 'items' => [['variant_id' => $product->defaultVariant->id, 'quantity' => 1]]]);
    $order = Order::query()->sole();

    $this->actingAs($alice)->get(route('pos.orders.show', $order))->assertOk();
    $this->actingAs($alice)->get(route('pos.orders'))->assertInertia(fn ($page) => $page->has('orders.data', 1));
    $this->actingAs($bob)->get(route('pos.orders.show', $order))->assertNotFound();
    $this->actingAs($bob)->get(route('pos.orders'))->assertInertia(fn ($page) => $page->has('orders.data', 0));
});

it('keeps a company account: orders minus payments, cancelled orders excluded', function () {
    $rep = salesRep();
    $admin = staffUser('admin');
    $company = makeCompany();
    $product = makeSimpleProduct(price: '100.00');

    foreach ([2, 1] as $qty) {
        $this->actingAs($rep)->post(route('pos.orders.store'), ['company_id' => $company->id, 'items' => [['variant_id' => $product->defaultVariant->id, 'quantity' => $qty]]]);
    }
    expect($company->fresh()->balance())->toBe(30000);

    app(ChangeOrderStatus::class)->handle(Order::query()->latest('id')->first(), OrderStatus::Cancelled, $admin);
    expect($company->fresh()->balance())->toBe(20000);

    $this->actingAs($admin)->post(route('admin.companies.payments.store', $company), [
        'amount' => '150', 'method' => 'cheque', 'reference' => '12345', 'paid_at' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect($company->fresh()->balance())->toBe(5000);

    $this->get(route('admin.companies.show', $company))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/Companies/Show')->has('statement', 3));

    // History protects the company from deletion.
    $this->delete(route('admin.companies.destroy', $company))->assertSessionHas('error');
    expect(Company::query()->whereKey($company->id)->exists())->toBeTrue();
});

it('lets company managers create companies and blocks reps and product managers', function () {
    $this->actingAs(salesRep())->get(route('admin.companies.index'))->assertForbidden();
    $this->actingAs(staffUser('product-manager'))->get(route('admin.companies.index'))->assertForbidden();

    $this->actingAs(staffUser('order-manager'))->post(route('admin.companies.store'), [
        'name' => 'سوبر ماركت الأمل', 'phone' => '059-111 2222', 'city' => 'جنين', 'is_active' => true,
    ])->assertRedirect();

    expect(Company::query()->sole()->phone)->toBe('0591112222');

    $this->post(route('admin.companies.store'), ['name' => 'سوبر ماركت الأمل'])->assertSessionHasErrors('name');
});
