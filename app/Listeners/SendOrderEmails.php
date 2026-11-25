<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Mail\OrderMail;
use App\Settings\MailSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * New order → a copy to every store inbox (Settings → Email) and a confirmation to the customer.
 * Status change → the customer is told (when they left an email). Each switch lives in MailSettings.
 * Queued itself (after commit): a mail problem can never turn a placed order into an error page.
 */
class SendOrderEmails implements ShouldQueue
{
    public function __construct(private MailSettings $settings) {}

    public function handleOrderPlaced(OrderPlaced $event): void
    {
        $order = $event->order;

        if ($this->settings->notify_new_order && $recipients = $this->settings->recipients()) {
            Mail::to($recipients)->queue(new OrderMail($order, 'new_order', 'ar'));
        }

        if ($this->settings->customer_confirmation && $order->customer_email) {
            Mail::to($order->customer_email, $order->customer_name)->queue(new OrderMail($order, 'confirmation', $order->locale ?: 'ar'));
        }
    }

    public function handleOrderStatusChanged(OrderStatusChanged $event): void
    {
        $order = $event->order;

        if (! $this->settings->customer_status_updates || ! $order->customer_email || $event->to === OrderStatus::Pending) {
            return;
        }

        Mail::to($order->customer_email, $order->customer_name)->queue(new OrderMail($order, 'status', $order->locale ?: 'ar', $event->to));
    }
}
