<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\StockStatus;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeOrderStatus
{
    public function handle(Order $order, OrderStatus $next, User $actor, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $next, $actor, $note): Order {
            // Re-read the status under a row lock: two simultaneous "cancel" clicks must not restock twice.
            $current = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $current->status->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => __('orders.invalid_transition', ['from' => $current->status->value, 'to' => $next->value]),
                ]);
            }

            if ($next === OrderStatus::Cancelled) {
                $this->restock($order);
            }

            $order->update(['status' => $next]);
            OrderStatusChanged::dispatch($order, $current->status, $next); // after commit

            if ($note) {
                $order->notes()->create(['user_id' => $actor->id, 'body' => $note]);
            }

            return $order;
        });
    }

    /**
     * Returns the quantities to stock. Goes through Eloquent so a variant that sold out becomes
     * purchasable again and the catalog caches are flushed by CatalogCacheObserver.
     */
    public function restock(Order $order): void
    {
        $order->loadMissing('items');

        $quantities = $order->items
            ->filter(fn (OrderItem $item) => $item->product_variant_id !== null)
            ->groupBy('product_variant_id')
            ->map(fn ($items) => (int) $items->sum('quantity'));

        if ($quantities->isEmpty()) {
            return;
        }

        $variants = ProductVariant::query()->whereIn('id', $quantities->keys())->where('manage_stock', true)->lockForUpdate()->get();

        foreach ($variants as $variant) {
            $variant->stock_quantity = (int) $variant->stock_quantity + $quantities[$variant->id];
            if ($variant->stock_quantity > 0 && $variant->stock_status === StockStatus::OutOfStock) {
                $variant->stock_status = StockStatus::InStock;
            }
            $variant->save();
        }
    }
}
