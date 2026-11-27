<?php

namespace App\Console\Commands;

use App\Actions\Invoices\IssueInvoice;
use App\Listeners\IssueInvoiceOnConfirmation;
use App\Models\Order;
use Illuminate\Console\Command;

/** Issues invoices for orders confirmed before invoicing existed, oldest first (dated when they were placed). */
class BackfillInvoices extends Command
{
    protected $signature = 'invoices:backfill {--dry-run : Only count the orders that would get an invoice}';

    protected $description = 'Issue invoices for already-confirmed orders that have none';

    public function handle(IssueInvoice $issue): int
    {
        $query = Order::query()
            ->whereIn('status', array_map(fn ($s) => $s->value, IssueInvoiceOnConfirmation::ISSUING_STATUSES))
            ->whereDoesntHave('invoice')
            ->orderBy('placed_at')->orderBy('id');

        $count = (clone $query)->count();
        if ($this->option('dry-run')) {
            $this->info("{$count} orders would get an invoice.");

            return self::SUCCESS;
        }

        activity()->withoutLogging(function () use ($query, $issue): void {
            $query->each(fn (Order $order) => $issue->handle($order, $order->status, $order->placed_at));
        });
        $this->info("Issued {$count} invoices.");

        return self::SUCCESS;
    }
}
