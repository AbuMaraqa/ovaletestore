<?php

use App\Actions\Invoices\IssueInvoice;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Mail\OrderMail;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Settings\InvoiceSettings;

/** A storefront order (cart → checkout) by the current visitor. */
function invoiceTestOrder(): Order
{
    return placeWebOrder(2, '12.50');
}

it('issues a sequential invoice when the order is confirmed, once', function () {
    $first = invoiceTestOrder();
    $second = invoiceTestOrder();
    expect($first->invoice()->exists())->toBeFalse(); // pending orders have none

    $this->actingAs(staffUser('admin'));
    $this->patch(route('admin.orders.status', $first), ['status' => 'confirmed'])->assertSessionHasNoErrors();
    $this->patch(route('admin.orders.status', $second), ['status' => 'completed'])->assertSessionHasNoErrors(); // jump: issued too
    $this->patch(route('admin.orders.status', $first), ['status' => 'processing'])->assertSessionHasNoErrors();

    $year = now()->year;
    expect(Invoice::query()->count())->toBe(2)
        ->and($first->invoice()->sole()->number)->toBe("INV-{$year}-00001")
        ->and($first->invoice()->sole()->issued_on_status)->toBe('confirmed')
        ->and($second->invoice()->sole()->number)->toBe("INV-{$year}-00002");

    expect(app(IssueInvoice::class)->handle($first->fresh())->number)->toBe("INV-{$year}-00001"); // idempotent
});

it('serves the invoice and the shipping label as PDFs to staff', function () {
    $order = invoiceTestOrder();
    $this->actingAs(staffUser('admin'));

    $this->get(route('admin.orders.invoice', $order))->assertNotFound(); // not issued yet
    $this->get(route('admin.orders.label', $order))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    app(IssueInvoice::class)->handle($order, OrderStatus::Confirmed);
    $response = $this->get(route('admin.orders.invoice', $order))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr((string) $response->getContent(), 0, 5))->toBe('%PDF-');

    $this->actingAs(User::factory()->create()->assignRole('customer'))->get(route('admin.orders.label', $order))->assertForbidden();
});

it('lets a customer download only the invoice of their own order', function () {
    $owner = User::factory()->create()->assignRole('customer');
    $other = User::factory()->create()->assignRole('customer');
    $order = invoiceTestOrder();
    $order->forceFill(['user_id' => $owner->id])->save();
    app(IssueInvoice::class)->handle($order, OrderStatus::Confirmed);

    $this->actingAs($owner)->get(route('account.orders.invoice', ['number' => $order->number]))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$order->invoice()->sole()->number.'.pdf"');
    $this->actingAs($other)->get(route('account.orders.invoice', ['number' => $order->number]))->assertNotFound();
});

it('attaches the invoice to the email of the status change that issued it', function () {
    $order = invoiceTestOrder();
    app(IssueInvoice::class)->handle($order, OrderStatus::Confirmed);

    $confirmed = new OrderMail($order->fresh(), 'status', 'ar', OrderStatus::Confirmed);
    $shipped = new OrderMail($order->fresh(), 'status', 'ar', OrderStatus::Shipped);

    expect($confirmed->attachments())->toHaveCount(1)->and($shipped->attachments())->toBe([]);
});

it('saves the invoice business details', function () {
    $this->actingAs(staffUser('admin'));
    $this->put(route('admin.settings.update', 'invoice'), [
        'business_name' => 'أوفاليت لمستلزمات الحلويات', 'registration_number' => '562233445', 'address' => 'دورا', 'phone' => '0599000000',
        'footer_note' => ['ar' => 'شكراً لكم'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $settings = app(InvoiceSettings::class)->refresh();
    expect($settings->registration_number)->toBe('562233445')->and($settings->footer_note)->toBe(['ar' => 'شكراً لكم']);
});

it('prints the company statement and the sales order of a company order as PDFs', function () {
    $company = Company::query()->create(['name' => 'شركة الطباعة', 'phone' => '0599000000']);
    $rep = User::factory()->create(['is_active' => true])->assignRole('sales-rep');
    $order = Order::query()->create([
        'number' => 'POS-PRINT-1', 'company_id' => $company->id, 'sales_rep_id' => $rep->id, 'status' => OrderStatus::Pending,
        'channel' => OrderChannel::Pos, 'payment_method' => PaymentMethod::OnAccount,
        'customer_name' => $company->name, 'currency' => 'ILS', 'subtotal' => 5000, 'total' => 5000, 'locale' => 'ar', 'placed_at' => now(),
    ]);

    $this->actingAs(staffUser('admin'));
    $this->get(route('admin.companies.statement', $company))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('admin.orders.sales-order', $order))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($rep)->get(route('pos.orders.pdf', $order))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $other = User::factory()->create(['is_active' => true])->assignRole('sales-rep');
    $this->actingAs($other)->get(route('pos.orders.pdf', $order))->assertNotFound(); // another rep's order
});
