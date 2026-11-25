<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Removes an order (items and notes cascade). Goods that were reserved but never left the shop go
 * back to stock first. The status is re-read under a row lock, so a cancel running at the same moment
 * (which restocks too) can never be counted twice.
 */
class DeleteOrder
{
    public function __construct(private ChangeOrderStatus $statuses) {}

    public function handle(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $current = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($current->status, [OrderStatus::Completed, OrderStatus::Shipped, OrderStatus::Cancelled], true)) {
                $this->statuses->restock($current);
            }

            $current->delete();
        });
    }
}
