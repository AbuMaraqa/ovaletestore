<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Compares the WooCommerce order export with what OrderSeeder wrote: counts per status, money totals,
 * line items and per-order totals. Fails when anything differs.
 */
#[Signature('woo:verify-orders {--path= : Export file (default: database/data/orders/orders.json)}')]
#[Description('Verify imported WooCommerce orders against the export')]
class WooVerifyOrders extends Command
{
    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('data/orders/orders.json');
        if (! File::exists($path)) {
            $this->components->error("{$path} not found — run php artisan woo:export-orders first.");

            return self::FAILURE;
        }

        $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        $source = collect($data['orders'] ?? []);
        $local = Order::query()->whereNotNull('source_id')->withCount('items')->get()->keyBy('source_id');

        $missing = $source->pluck('source_id')->diff($local->keys())->values();
        $mismatched = $source->filter(function (array $row) use ($local) {
            $order = $local->get($row['source_id']);

            return $order && ($order->total !== $row['total'] || $order->status->value !== $row['status']
                || $order->items_count !== count($row['items']));
        })->pluck('source_id')->values();

        $sum = fn ($items, string $key) => number_format($items->sum($key) / 100, 2);
        $rows = [
            ['Orders', (int) ($data['source_total'] ?? $source->count()), $source->count(), $local->count()],
            ['Order total (₪)', '-', $sum($source, 'total'), $sum($local, 'total')],
            ['Delivery total (₪)', '-', $sum($source, 'shipping_total'), $sum($local, 'shipping_total')],
            ['Line items', '-', $source->sum(fn ($o) => count($o['items'])),
                OrderItem::query()->whereIn('order_id', $local->pluck('id'))->count()],
            ['Items linked to a product', '-', '-',
                OrderItem::query()->whereIn('order_id', $local->pluck('id'))->whereNotNull('product_id')->count()],
            ['Placed from a customer account', '-', $source->whereNotNull('customer_source_id')->count(),
                $local->whereNotNull('user_id')->count()],
        ];
        foreach ($source->countBy('status')->sortKeys() as $status => $count) {
            $rows[] = ["Status: {$status}", '-', $count, $local->filter(fn (Order $o) => $o->status->value === $status)->count()];
        }

        $this->table(['Check', 'WooCommerce', 'Export', 'Imported'], $rows);
        $this->line('Missing orders: '.($missing->isEmpty() ? 'none' : $missing->implode(', ')));
        $this->line('Orders with a different total/status/item count: '.($mismatched->isEmpty() ? 'none' : $mismatched->implode(', ')));

        // Account orders whose WooCommerce customer was not imported (e.g. placed by a WordPress administrator).
        $unlinked = $source->filter(fn (array $row) => ($row['customer_source_id'] ?? null) && ! $local->get($row['source_id'])?->user_id);
        $this->line('Account orders without a local account: '.($unlinked->isEmpty() ? 'none'
            : $unlinked->map(fn ($r) => "{$r['number']} (customer #{$r['customer_source_id']})")->implode(', ')));

        $ok = $missing->isEmpty() && $mismatched->isEmpty() && $source->count() === (int) ($data['source_total'] ?? $source->count());

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
