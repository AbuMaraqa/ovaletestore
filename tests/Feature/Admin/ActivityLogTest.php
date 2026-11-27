<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

it('records a variant price change on its product history with old and new values, user and IP', function () {
    $admin = staffUser('admin');
    $product = makeSimpleProduct('شوكولاتة', '10.00');

    $this->actingAs($admin);
    $product->defaultVariant->update(['regular_price' => 1250]);

    $entry = Activity::query()->where('subject_type', $product->defaultVariant::class)->where('event', 'updated')->latest('id')->firstOrFail();
    expect($entry->log_name)->toBe('catalog')
        ->and($entry->attribute_changes['old']['regular_price'])->toBe(1000)
        ->and($entry->attribute_changes['attributes']['regular_price'])->toBe(1250)
        ->and($entry->parent_type)->toBe(Product::class)
        ->and($entry->parent_id)->toBe($product->id)
        ->and($entry->causer_id)->toBe($admin->id)
        ->and($entry->getProperty('ip'))->not->toBeNull();

    $this->getJson(route('admin.activity.record', ['type' => 'product', 'id' => $product->id]))
        ->assertOk()
        ->assertJsonFragment(['event' => 'updated', 'child' => 'variant']);
});

it('never logs passwords or two-factor secrets', function () {
    $user = User::factory()->create();
    $user->update(['password' => 'Another-Secret-1', 'name' => 'اسم جديد']);

    $entry = Activity::query()->where('subject_type', User::class)->where('event', 'updated')->latest('id')->firstOrFail();
    expect(array_keys($entry->attribute_changes['attributes']))->toBe(['name']);
});

it('logs sign-ins and failed sign-in attempts', function () {
    $user = User::factory()->create(['password' => 'Secret-Pass-123'])->assignRole('customer');

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Secret-Pass-123']);

    expect(Activity::query()->where('log_name', 'auth')->pluck('event')->all())->toContain('login_failed', 'login');
});

it('shows the activity screen only with the activity.view permission', function () {
    $this->actingAs(staffUser('order-manager'))->get(route('admin.activity.index'))->assertForbidden();
    $this->actingAs(staffUser('order-manager'))->getJson(route('admin.activity.record', ['type' => 'product', 'id' => 1]))->assertForbidden();

    $this->actingAs(staffUser('admin'))->get(route('admin.activity.index', ['filter' => ['log' => 'catalog'], 'per_page' => 10]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Activity/Index')->where('state.per_page', 10)->where('state.filter.log', 'catalog'));
});

it('logs settings changes with secrets masked', function () {
    $admin = staffUser('admin');
    $this->actingAs($admin)->put(route('admin.settings.update', 'mail'), [
        'from_name' => 'متجر', 'order_recipients' => "orders@example.test\nboss@example.test",
        'notify_new_order' => true, 'customer_confirmation' => true, 'customer_status_updates' => false,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $entry = Activity::query()->where('log_name', 'settings')->where('event', 'updated')->latest('id')->firstOrFail();
    expect($entry->attribute_changes['attributes']['order_recipients'])->toBe(['orders@example.test', 'boss@example.test'])
        ->and($entry->attribute_changes['attributes']['customer_status_updates'])->toBeFalse();
});

it('shows each viewer only the log channels they may see', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo(['admin.access', 'activity.view', 'products.view']);
    $order = Order::query()->create([
        'number' => 'T-1', 'status' => 'pending', 'channel' => 'web', 'payment_method' => 'cod', 'customer_name' => 'سرّي',
        'customer_phone' => '0590000000', 'locale' => 'ar', 'currency' => 'ILS', 'subtotal' => 0, 'discount_total' => 0,
        'shipping_total' => 0, 'total' => 0, 'placed_at' => now(),
    ]);
    makeSimpleProduct('ظاهر');

    $this->actingAs($viewer)->get(route('admin.activity.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('table.filters', fn ($filters) => collect(collect($filters)->firstWhere('key', 'log')['options'])->pluck('value')->all() === ['catalog'])
            ->where('activities.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['log'] === 'catalog')));
    $this->actingAs($viewer)->getJson(route('admin.activity.record', ['type' => 'order', 'id' => $order->id]))->assertForbidden();
});

it('stores only a valid email for failed sign-ins', function () {
    $this->post(route('login.store'), ['email' => 'MyPassw0rd!', 'password' => 'x']);

    expect(Activity::query()->where('event', 'login_failed')->latest('id')->firstOrFail()->getProperty('label'))->toBe('(invalid)');
});
