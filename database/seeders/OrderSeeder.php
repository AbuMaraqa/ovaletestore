<?php

namespace Database\Seeders;

use App\Migration\Woo\BilingualName;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNote;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Loads the historical WooCommerce orders written by `php artisan woo:export-orders`
 * (database/data/orders/orders.json — git-ignored, it holds customer data).
 *
 * - Idempotent: matched by `source_id`; items and notes are rebuilt, so re-running updates in place.
 * - History only: stock is never touched and no notification/activity event fires.
 * - Orders placed from a customer account link to it (CustomerSeeder runs first); guest orders stay guest.
 * - Items link to the migrated product (by WooCommerce product id) when it still exists;
 *   the name/SKU/price snapshot is kept either way.
 */
class OrderSeeder extends Seeder
{
    public function run(?string $path = null): void
    {
        $path ??= database_path('data/orders/orders.json');
        if (! File::exists($path)) {
            $this->command?->warn("OrderSeeder skipped: {$path} not found (run php artisan woo:export-orders).");

            return;
        }

        $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        $orders = (array) ($data['orders'] ?? []);

        $productSourceIds = collect($orders)->pluck('items')->flatten(1)->pluck('product_source_id')->filter()->unique()->values();
        $products = Product::withTrashed()->whereIn('source_id', $productSourceIds)
            ->with(['translations', 'defaultVariant'])->get()->keyBy('source_id');
        $shipping = ShippingMethod::query()->whereNotNull('code')->pluck('id', 'code');
        // Only orders placed while signed in belong to an account (WooCommerce customer_id); guest
        // orders stay guest orders even when the email matches an account.
        $customerIds = collect($orders)->pluck('customer_source_id')->filter()->unique()->values();
        $users = User::query()->whereIn('source_id', $customerIds)->pluck('id', 'source_id');

        $counts = ['orders' => 0, 'items' => 0, 'unlinked_items' => 0];

        Model::withoutEvents(function () use ($orders, $products, $shipping, $users, &$counts) {
            foreach ($orders as $row) {
                DB::transaction(function () use ($row, $products, $shipping, $users, &$counts) {
                    $order = Order::query()->where('source_id', $row['source_id'])->first() ?? new Order;
                    $order->timestamps = false;
                    $order->forceFill([
                        'source_id' => $row['source_id'],
                        'number' => $row['number'],
                        'user_id' => ($row['customer_source_id'] ?? null) ? $users->get($row['customer_source_id']) : null,
                        'status' => $row['status'],
                        'channel' => $row['channel'],
                        'payment_method' => $row['payment_method'],
                        'customer_name' => $row['customer_name'],
                        'customer_email' => $row['customer_email'],
                        'customer_phone' => $row['customer_phone'],
                        'shipping_city' => $row['shipping_city'],
                        'shipping_address' => $row['shipping_address'],
                        'shipping_method_id' => $row['shipping_method_code'] ? $shipping->get($row['shipping_method_code']) : null,
                        'shipping_method_name' => $row['shipping_method_name'],
                        'customer_note' => $row['customer_note'],
                        'currency' => $row['currency'],
                        'subtotal' => $row['subtotal'],
                        'discount_total' => $row['discount_total'],
                        'shipping_total' => $row['shipping_total'],
                        'total' => $row['total'],
                        'locale' => $row['locale'],
                        'placed_at' => Carbon::parse($row['placed_at']),
                        'created_at' => Carbon::parse($row['placed_at']),
                        'updated_at' => Carbon::parse($row['updated_at'] ?? $row['placed_at']),
                    ])->save();

                    $order->items()->delete();
                    foreach ($row['items'] as $item) {
                        /** @var Product|null $product */
                        $product = $item['product_source_id'] ? $products->get($item['product_source_id']) : null;
                        $counts['items']++;
                        $counts['unlinked_items'] += $product ? 0 : 1;

                        OrderItem::query()->create([
                            'order_id' => $order->id,
                            'product_id' => $product?->id,
                            'product_variant_id' => $product?->defaultVariant?->id,
                            'name' => mb_substr($product?->translate($row['locale'], true)?->name ?: $this->snapshotName($item['name'], $row['locale']), 0, 255),
                            'variant_label' => null,
                            'sku' => $item['sku'] ? mb_substr($item['sku'], 0, 64) : $product?->defaultVariant?->sku,
                            'unit_price' => $item['unit_price'],
                            'quantity' => $item['quantity'],
                            'line_total' => $item['line_total'],
                        ]);
                    }

                    $order->notes()->delete();
                    foreach ($row['notes'] as $note) {
                        $at = Carbon::parse($note['created_at'] ?? $row['placed_at']);
                        (new OrderNote)->forceFill([
                            'order_id' => $order->id,
                            'body' => $note['body'],
                            'is_customer_visible' => $note['is_customer_visible'],
                            'created_at' => $at,
                            'updated_at' => $at,
                        ])->save();
                    }

                    $counts['orders']++;
                });
            }
        });

        $this->command?->info("Orders: {$counts['orders']} · items: {$counts['items']} ({$counts['unlinked_items']} without a current product)");
    }

    /** Source names are "عربي.English": keep the half matching the order's language. */
    private function snapshotName(string $raw, string $locale): string
    {
        $split = BilingualName::split($raw, true, 2);

        return ($locale !== 'ar' ? $split['en'] : null) ?: ($split['ar'] ?: $raw);
    }
}
