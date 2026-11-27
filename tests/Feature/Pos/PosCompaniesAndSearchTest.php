<?php

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Spatie\Permission\Models\Role;

function posRep(): User
{
    return User::factory()->create(['is_active' => true])->assignRole('sales-rep');
}

function posOrderFor(User $rep, Company $company, string $number, OrderStatus $status = OrderStatus::Pending, ?string $placedAt = null): Order
{
    return Order::query()->create([
        'number' => $number, 'company_id' => $company->id, 'sales_rep_id' => $rep->id, 'status' => $status,
        'channel' => OrderChannel::Pos, 'payment_method' => PaymentMethod::OnAccount, 'customer_name' => $company->name,
        'customer_phone' => $company->phone, 'currency' => 'ILS', 'subtotal' => 1000, 'total' => 1000, 'locale' => 'ar',
        'placed_at' => $placedAt ? now()->parse($placedAt) : now(),
    ]);
}

it('lets a sales rep add a company and go straight to its order screen', function () {
    $this->actingAs(posRep());

    $this->get(route('pos.companies.create'))->assertOk();
    $response = $this->post(route('pos.companies.store'), [
        'name' => 'حلويات الأمل', 'contact_name' => 'أبو محمد', 'phone' => '059-911 2233', 'city' => 'الخليل', 'address' => 'شارع السلام',
        'notes' => 'should be ignored', 'is_active' => false,
    ])->assertSessionHasNoErrors();

    $company = Company::query()->where('name', 'حلويات الأمل')->sole();
    $response->assertRedirect(route('pos.create', $company));
    expect($company->phone)->toBe('0599112233')
        ->and($company->is_active)->toBeTrue()   // the POS cannot create inactive companies
        ->and($company->notes)->toBeNull();      // nor write internal notes

    $this->post(route('pos.companies.store'), ['name' => 'حلويات الأمل'])->assertSessionHasErrors('name');
});

it('lets a sales rep correct company details but not notes or the active flag', function () {
    $company = Company::query()->create(['name' => 'مخبز النور', 'phone' => '0590000000', 'notes' => 'دفع متأخر', 'is_active' => true]);
    $this->actingAs(posRep());

    $this->get(route('pos.companies.edit', $company))->assertOk();
    $this->put(route('pos.companies.update', $company), ['name' => 'مخبز النور الجديد', 'phone' => '0591111111', 'city' => 'رام الله', 'notes' => 'x', 'is_active' => false])
        ->assertSessionHasNoErrors()->assertRedirect(route('pos.create', $company));

    $company->refresh();
    expect($company->name)->toBe('مخبز النور الجديد')->and($company->city)->toBe('رام الله')
        ->and($company->notes)->toBe('دفع متأخر')->and($company->is_active)->toBeTrue();
});

it('blocks company management without the POS permissions', function () {
    $rep = posRep();
    $rep->syncPermissions([]);
    Role::findByName('sales-rep')->revokePermissionTo(['pos.companies.create', 'pos.companies.update']);
    $company = Company::query()->create(['name' => 'شركة', 'is_active' => true]);

    $this->actingAs($rep->fresh());
    $this->get(route('pos.companies.create'))->assertForbidden();
    $this->put(route('pos.companies.update', $company), ['name' => 'x'])->assertForbidden();
});

it('searches the rep own orders by number, company and phone, with status and date filters', function () {
    $rep = posRep();
    $alpha = Company::query()->create(['name' => 'شركة ألفا', 'phone' => '0597000001']);
    $beta = Company::query()->create(['name' => 'شركة بيتا', 'phone' => '0597000002']);
    posOrderFor($rep, $alpha, 'POS-A1', OrderStatus::Pending, '2026-11-01 10:00');
    posOrderFor($rep, $beta, 'POS-B1', OrderStatus::Completed, '2026-11-20 10:00');
    posOrderFor(posRep(), $alpha, 'POS-OTHER'); // another rep's order is never listed

    $this->actingAs($rep);
    $numbers = fn (array $query) => collect($this->get(route('pos.orders', $query))->assertOk()->viewData('page')['props']['orders']['data'])->pluck('number')->all();

    expect($numbers([]))->toEqualCanonicalizing(['POS-A1', 'POS-B1'])
        ->and($numbers(['q' => 'بيتا']))->toBe(['POS-B1'])
        ->and($numbers(['q' => '7000001']))->toBe(['POS-A1'])
        ->and($numbers(['q' => 'POS-A']))->toBe(['POS-A1'])
        ->and($numbers(['status' => 'completed']))->toBe(['POS-B1'])
        ->and($numbers(['from' => '2026-11-10']))->toBe(['POS-B1'])
        ->and($numbers(['until' => '2026-11-10']))->toBe(['POS-A1'])
        ->and($numbers(['q' => 'OTHER']))->toBe([]);
});
