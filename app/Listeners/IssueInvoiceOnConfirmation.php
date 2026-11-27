<?php

namespace App\Listeners;

use App\Actions\Invoices\IssueInvoice;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;

/**
 * The invoice is issued when an order is confirmed (owner's choice), or at whatever later step it jumps to
 * first. Runs synchronously so the status email (queued) can attach the PDF.
 */
class IssueInvoiceOnConfirmation
{
    public const ISSUING_STATUSES = [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed];

    public function __construct(private readonly IssueInvoice $issue) {}

    public function handle(OrderStatusChanged $event): void
    {
        if (in_array($event->to, self::ISSUING_STATUSES, true)) {
            $this->issue->handle($event->order, $event->to);
        }
    }
}
