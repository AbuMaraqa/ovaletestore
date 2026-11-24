<?php

use App\Enums\OrderStatus;
use App\Migration\Woo\WooOrderMapper;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\User;
use Database\Seeders\OrderSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/** A WooCommerce REST v3 order with fictional data. */
function wooOrder(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 7001, 'number' => '7001', 'status' => 'completed', 'created_via' => 'checkout', 'currency' => 'ILS',
        'payment_method_title' => 'الدفع عند الاستلام', 'customer_id' => 0, 'customer_note' => 'بعد العصر',
        'date_created' => '2026-10-01T12:00:00', 'date_created_gmt' => '2026-10-01T09:00:00',
        'date_modified' => '2026-10-02T12:00:00', 'date_modified_gmt' => '2026-10-02T09:00:00',
        'discount_total' => '0.00', 'shipping_total' => '20.00', 'total' => '68.50',
        'billing' => ['first_name' => 'سامي', 'last_name' => 'تجربة', 'company' => '', 'city' => 'نابلس', 'state' => 'رفيديا',
            'address_1' => 'شارع الجامعة', 'address_2' => '3', 'email' => 'Sami@Example.test', 'phone' => '0590000000'],
        'shipping' => [],
        'line_items' => [
            ['product_id' => 9101, 'variation_id' => 0, 'name' => 'مشروب.Drink', 'sku' => 'D-1', 'quantity' => 2, 'subtotal' => '33.00', 'total' => '33.00'],
            ['product_id' => 9999, 'variation_id' => 0, 'name' => 'منتج محذوف', 'sku' => '', 'quantity' => 1, 'subtotal' => '15.50', 'total' => '15.50'],
        ],
        'shipping_lines' => [['method_title' => 'الضفة الغربية', 'total' => '20.00']],
        'refunds' => [],
        'meta_data' => [['key' => 'trp_language', 'value' => 'en_US']],
    ], $overrides);
}

function writeOrderExport(array $orders): string
{
    $path = storage_path('framework/testing/orders-'.uniqid().'.json');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, json_encode(['source_total' => count($orders), 'orders' => $orders], JSON_UNESCAPED_UNICODE));

    return $path;
}

it('maps a WooCommerce order to agorot, our status, address, delivery region, locale and notes', function () {
    $row = WooOrderMapper::map(wooOrder(['status' => 'refunded', 'refunds' => [['total' => '-68.50', 'reason' => 'مرتجع']]]), [
        ['note' => '<p>تغيّرت حالة الطلب</p>', 'author' => 'Oswa', 'customer_note' => false, 'date_created_gmt' => '2026-10-02T09:00:00'],
    ]);

    expect($row)->toMatchArray([
        'source_id' => 7001, 'number' => 'WC-7001', 'status' => 'cancelled', 'source_status' => 'refunded',
        'channel' => 'web', 'payment_method' => 'cod', 'customer_name' => 'سامي تجربة', 'customer_email' => 'sami@example.test',
        'shipping_city' => 'نابلس', 'shipping_address' => 'رفيديا، شارع الجامعة، 3', 'shipping_method_code' => 'west_bank',
        'subtotal' => 4850, 'shipping_total' => 2000, 'total' => 6850, 'locale' => 'en',
        'placed_at' => '2026-10-01T09:00:00+00:00',
    ])
        ->and($row['items'][0])->toBe(['product_source_id' => 9101, 'name' => 'مشروب.Drink', 'sku' => 'D-1', 'unit_price' => 1650, 'quantity' => 2, 'line_total' => 3300])
        ->and($row['notes'][0]['body'])->toContain('WooCommerce #7001 (refunded)')->toContain('68.50: مرتجع')
        ->and($row['notes'][1]['body'])->toBe('Oswa: تغيّرت حالة الطلب');

    expect(WooOrderMapper::map(wooOrder(['created_via' => 'woocommerce-pos', 'status' => 'pos-open']))['channel'])->toBe('pos');
});

it('seeds orders idempotently without touching stock, linking products, customers and delivery regions', function () {
    $product = makeSimpleProduct('مشروب', '16.50', ['manage_stock' => true, 'stock_quantity' => 5]);
    $product->forceFill(['source_id' => 9101])->save();
    $customer = User::factory()->create(['email' => 'sami@example.test']);
    $path = writeOrderExport([
        WooOrderMapper::map(wooOrder(), [['note' => 'اتصلنا بالزبون', 'author' => 'Oswa', 'customer_note' => true, 'date_created_gmt' => '2026-10-01T10:00:00']]),
        WooOrderMapper::map(wooOrder(['id' => 7002, 'number' => '7002', 'status' => 'cancelled'])),
    ]);

    (new OrderSeeder)->run($path);
    (new OrderSeeder)->run($path); // re-run: updates in place, no duplicates

    expect(Order::query()->count())->toBe(2);
    $order = Order::query()->where('source_id', 7001)->with(['items', 'notes'])->sole();

    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->number)->toBe('WC-7001')
        ->and($order->user_id)->toBe($customer->id)
        ->and($order->total)->toBe(6850)
        ->and($order->shipping_method_id)->toBe(ShippingMethod::query()->where('code', 'west_bank')->value('id'))
        ->and($order->placed_at->toIso8601String())->toBe('2026-10-01T09:00:00+00:00')
        ->and($order->items)->toHaveCount(2)
        ->and($order->items->firstWhere('product_id', $product->id)->name)->toStartWith('Test product') // en order → product's en name
        ->and($order->items->whereNull('product_id'))->toHaveCount(1)
        ->and($order->notes)->toHaveCount(2)
        ->and($order->notes->firstWhere('is_customer_visible', true)->created_at->toIso8601String())->toBe('2026-10-01T10:00:00+00:00')
        ->and($product->defaultVariant()->value('stock_quantity'))->toBe(5);

    $this->artisan('woo:verify-orders', ['--path' => $path])->assertSuccessful();

    File::delete($path);
});

it('fails verification when an exported order was not imported', function () {
    $path = writeOrderExport([WooOrderMapper::map(wooOrder())]);

    $this->artisan('woo:verify-orders', ['--path' => $path])->assertFailed();

    File::delete($path);
});

it('exports orders read-only through a wp-admin login', function () {
    config(['services.woocommerce.base_url' => 'https://shop.test', 'services.woocommerce.admin_user' => 'u', 'services.woocommerce.admin_password' => 'p']);
    Http::fake([
        'shop.test/wp-login.php' => fn ($request) => $request->method() === 'POST'
            ? Http::response('', 302, ['Location' => 'https://shop.test/wp-admin/'])
            : Http::response('login form'),
        'shop.test/wp-admin/admin-ajax.php*' => Http::response('abcdef1234'),
        'shop.test/wp-json/wc/v3/orders/7001/notes*' => Http::response([]),
        'shop.test/wp-json/wc/v3/orders*' => Http::response([wooOrder()], 200, ['X-WP-Total' => '1', 'X-WP-TotalPages' => '1']),
    ]);
    $path = storage_path('framework/testing/export-'.uniqid().'.json');

    $this->artisan('woo:export-orders', ['--path' => $path])->assertSuccessful();

    expect(json_decode(File::get($path), true)['orders'][0]['number'])->toBe('WC-7001');
    Http::assertSent(fn ($request) => $request->hasHeader('X-WP-Nonce', 'abcdef1234') || ! str_contains($request->url(), 'wp-json'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'wp-json') && $request->method() !== 'GET');

    File::delete($path);
});

it('refuses to export when the WordPress login fails', function () {
    config(['services.woocommerce.base_url' => 'https://shop.test', 'services.woocommerce.admin_user' => 'u', 'services.woocommerce.admin_password' => 'bad']);
    Http::fake(['shop.test/wp-login.php' => Http::response('login form'), 'shop.test/wp-admin/admin-ajax.php*' => Http::response('0')]);

    $this->artisan('woo:export-orders', ['--path' => storage_path('framework/testing/never.json')])->assertFailed();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'wp-json'));
});
