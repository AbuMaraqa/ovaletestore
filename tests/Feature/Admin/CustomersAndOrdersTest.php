<?php

use App\Enums\OrderStatus;
use App\Mail\OrderMail;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Settings\MailSettings;
use Database\Seeders\CustomerSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

function placeAdminTestOrder(array $payload = []): Order
{
    $product = makeSimpleProduct(price: '10.00', variant: ['manage_stock' => true, 'stock_quantity' => 5]);
    test()->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 2]);
    test()->post(route('storefront.checkout.store'), [...checkoutPayload(), ...$payload])->assertSessionHasNoErrors();

    return Order::query()->latest('id')->firstOrFail();
}

it('lists customers with their orders and spend, never staff', function () {
    $customer = User::factory()->create(['name' => 'زبون وفي'])->assignRole('customer');
    $order = placeAdminTestOrder();
    $order->forceFill(['user_id' => $customer->id])->save();
    $staff = staffUser('order-manager');

    $this->actingAs(staffUser('admin'))->get(route('admin.customers.index', ['filter' => ['orders' => 'with']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Customers/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'زبون وفي')
            ->where('customers.data.0.orders_count', 1));

    $this->actingAs(staffUser('admin'))->get(route('admin.customers.show', $staff))->assertNotFound();
    $this->actingAs(staffUser('admin'))->get(route('admin.customers.show', $customer))->assertOk();
});

it('imports WooCommerce customers idempotently and links an existing account by email', function () {
    $existing = User::factory()->create(['email' => 'old@example.test']);
    $path = storage_path('framework/testing/customers-'.uniqid().'.json');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, json_encode(['customers' => [
        ['source_id' => 11, 'name' => 'ليلى', 'email' => 'layla@example.test', 'phone' => '0590000001', 'created_at' => '2025-06-01T10:00:00'],
        ['source_id' => 12, 'name' => 'Old', 'email' => 'old@example.test', 'phone' => null, 'created_at' => null],
    ]], JSON_UNESCAPED_UNICODE));

    (new CustomerSeeder)->run($path);
    (new CustomerSeeder)->run($path);

    $layla = User::query()->where('source_id', 11)->sole();
    expect($layla->hasRole('customer'))->toBeTrue()
        ->and($layla->created_at->toDateString())->toBe('2025-06-01')
        ->and($existing->fresh()->source_id)->toBe(12)
        ->and(User::query()->whereIn('email', ['layla@example.test', 'old@example.test'])->count())->toBe(2);
});

it('deletes an order only with permission and returns its stock', function () {
    $order = placeAdminTestOrder();
    $variant = ProductVariant::query()->findOrFail($order->items()->firstOrFail()->product_variant_id);
    expect($variant->fresh()->stock_quantity)->toBe(3);

    $this->actingAs(staffUser('order-manager'))->delete(route('admin.orders.destroy', $order))->assertForbidden();
    $this->actingAs(staffUser('admin'))->delete(route('admin.orders.destroy', $order))->assertRedirect(route('admin.orders.index'));

    expect(Order::query()->whereKey($order->id)->exists())->toBeFalse()
        ->and($variant->fresh()->stock_quantity)->toBe(5);
});

it('emails the store and the customer about a new order and the customer about status changes', function () {
    Mail::fake();
    $settings = app(MailSettings::class);
    $settings->order_recipients = ['orders@example.test'];
    $settings->save();

    $order = placeAdminTestOrder(['customer_email' => 'buyer@example.test']);

    Mail::assertQueued(OrderMail::class, fn (OrderMail $m) => $m->kind === 'new_order' && $m->hasTo('orders@example.test'));
    Mail::assertQueued(OrderMail::class, fn (OrderMail $m) => $m->kind === 'confirmation' && $m->hasTo('buyer@example.test'));

    $this->actingAs(staffUser('order-manager'))->patch(route('admin.orders.status', $order), ['status' => 'shipped']);
    Mail::assertQueued(OrderMail::class, fn (OrderMail $m) => $m->kind === 'status' && $m->status === OrderStatus::Shipped && $m->hasTo('buyer@example.test'));
    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('respects the email switches and renders the order email', function () {
    Mail::fake();
    $settings = app(MailSettings::class);
    $settings->order_recipients = ['orders@example.test'];
    $settings->notify_new_order = false;
    $settings->customer_confirmation = false;
    $settings->save();

    $order = placeAdminTestOrder(['customer_email' => 'buyer@example.test']);
    Mail::assertNothingQueued();

    $html = (new OrderMail($order, 'confirmation', 'ar'))->render();
    expect($html)->toContain($order->number)->toContain('dir="rtl"')->toContain('شكراً لطلبك');

    // English customer: English text and /en/ links even without a request locale (queue worker).
    $order->forceFill(['user_id' => User::factory()->create()->id])->save();
    $english = (new OrderMail($order->fresh(), 'confirmation', 'en'))->render();
    expect($english)->toContain('dir="ltr"')->toContain('Thank you for your order')->toContain('/en/account/orders/');
});

it('validates order recipient addresses', function () {
    $this->actingAs(staffUser('admin'))->put(route('admin.settings.update', 'mail'), ['order_recipients' => "ok@example.test\nnot-an-email"])
        ->assertSessionHasErrors('order_recipients');
});

it('applies product bulk actions only with the right permission', function () {
    $a = makeSimpleProduct('أ');
    $b = makeSimpleProduct('ب');

    $this->actingAs(staffUser('content-manager'))->post(route('admin.tables.bulk', 'products'), ['action' => 'delete', 'ids' => [$a->id]])->assertForbidden();
    $this->actingAs(staffUser('product-manager'))->post(route('admin.tables.bulk', 'products'), ['action' => 'draft', 'ids' => [$a->id, $b->id]])->assertSessionHasNoErrors();

    expect($a->fresh()->status->value)->toBe('draft')->and($b->fresh()->status->value)->toBe('draft');
});

it('lets staff with customers.create add a customer account, optionally emailing a set-password link', function () {
    Notification::fake();
    $this->actingAs(staffUser('order-manager'))->get(route('admin.customers.create'))->assertForbidden();

    $this->actingAs(staffUser('admin'));
    $this->get(route('admin.customers.create'))->assertOk();
    $this->post(route('admin.customers.store'), [
        'name' => 'سارة أحمد', 'email' => 'Sara.Shop@example.com', 'phone' => '0599111222', 'locale' => 'ar',
        'is_active' => true, 'password' => '', 'send_password_link' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $customer = User::query()->where('email', 'sara.shop@example.com')->sole();
    expect($customer->hasExactRoles('customer'))->toBeTrue()
        ->and($customer->email_verified_at)->not->toBeNull();
    Notification::assertSentTo($customer, ResetPassword::class);

    $this->post(route('admin.customers.store'), ['name' => 'x', 'email' => 'sara.shop@example.com', 'locale' => 'ar'])->assertSessionHasErrors('email');
});

it('prefills a signed-in customer checkout with their last delivery address, and the cart assumes no region', function () {
    $customer = User::factory()->create(['phone' => null])->assignRole('customer');
    $previous = placeAdminTestOrder(['shipping_city' => 'الخليل', 'shipping_address' => 'شارع السلام 5']);
    $previous->forceFill(['user_id' => $customer->id])->save();

    $product = makeSimpleProduct(price: '15.00');
    $this->actingAs($customer)->post(route('storefront.cart.store'), ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);

    $this->get(route('storefront.cart'))->assertInertia(fn ($page) => $page->where('cart.shipping_selected', false));
    $this->get(route('storefront.checkout'))->assertInertia(fn ($page) => $page
        ->where('prefill.shipping_city', 'الخليل')
        ->where('prefill.shipping_address', 'شارع السلام 5')
        ->where('prefill.customer_phone', $previous->customer_phone)
        ->where('cart.shipping_selected', false));
});
