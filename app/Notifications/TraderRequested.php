<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Admin header bell: a customer asked for trader prices and waits for approval. */
class TraderRequested extends Notification
{
    use Queueable;

    public function __construct(public User $trader) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'trader.requested',
            'user_id' => $this->trader->id,
            'customer' => $this->trader->name,
            'number' => $this->trader->business_name,
        ];
    }
}
