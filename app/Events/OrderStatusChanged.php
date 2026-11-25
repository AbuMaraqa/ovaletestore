<?php

namespace App\Events;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired after a staff member moves an order to another status (and the change is committed). */
class OrderStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order, public OrderStatus $from, public OrderStatus $to) {}
}
