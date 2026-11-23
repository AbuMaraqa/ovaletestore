<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Stored in the `notifications` table; shown in the admin header bell (polled). */
class NewOrderPlaced extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** Only display data — no customer contact details stored in the notification payload. */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order.placed',
            'order_id' => $this->order->id,
            'number' => $this->order->number,
            'customer' => $this->order->customer_name,
            'total' => Money::format($this->order->total, $this->order->currency),
        ];
    }
}
