<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Models\User;
use App\Notifications\NewOrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** Notifies every active staff member who may see orders (queued: checkout response stays fast). */
class NotifyStaffOfNewOrder implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        $staff = User::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->permission('orders.view')->orWhereHas('roles', fn ($r) => $r->where('name', 'super-admin')))
            ->get();

        Notification::send($staff, new NewOrderPlaced($event->order));
    }
}
