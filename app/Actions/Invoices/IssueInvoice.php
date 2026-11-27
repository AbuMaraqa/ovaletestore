<?php

namespace App\Actions\Invoices;

use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gives an order its invoice: the next number of the year (INV-2026-00042), once. Idempotent and safe
 * under concurrency: the order row is locked, and the (year, sequence) unique index backs the counter.
 */
class IssueInvoice
{
    public function handle(Order $order, ?OrderStatus $status = null, ?Carbon $at = null): Invoice
    {
        return DB::transaction(function () use ($order, $status, $at): Invoice {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existing = Invoice::query()->where('order_id', $locked->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            $at ??= now();
            $year = (int) $at->format('Y');
            $sequence = (int) Invoice::query()->where('year', $year)->lockForUpdate()->max('sequence') + 1;

            return Invoice::query()->create([
                'order_id' => $locked->id,
                'number' => Invoice::formatNumber($year, $sequence),
                'year' => $year,
                'sequence' => $sequence,
                'issued_on_status' => ($status ?? $locked->status)->value,
                'issued_at' => $at,
            ]);
        });
    }
}
