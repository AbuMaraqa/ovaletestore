<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can('orders.view');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can('orders.update_status');
    }

    /** A company's on-account order is its debt: it can only be removed after it was cancelled. */
    public function delete(User $user, Order $order): bool
    {
        return $user->can('orders.delete')
            && ($order->payment_method !== PaymentMethod::OnAccount || $order->status === OrderStatus::Cancelled);
    }
}
