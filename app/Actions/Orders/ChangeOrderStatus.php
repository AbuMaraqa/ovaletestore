<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeOrderStatus
{
    public function handle(Order $order, OrderStatus $next, User $actor, ?string $note = null): Order
    {
        if (! $order->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => __('orders.invalid_transition', ['from' => $order->status->value, 'to' => $next->value]),
            ]);
        }

        return DB::transaction(function () use ($order, $next, $actor, $note): Order {
            if ($next === OrderStatus::Cancelled) {
                $this->restock($order);
            }

            $order->update(['status' => $next]);

            if ($note) {
                $order->notes()->create(['user_id' => $actor->id, 'body' => $note]);
            }

            return $order;
        });
    }

    private function restock(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->product_variant_id) {
                DB::table('product_variants')
                    ->where('id', $item->product_variant_id)
                    ->where('manage_stock', true)
                    ->increment('stock_quantity', $item->quantity);
            }
        }
    }
}
