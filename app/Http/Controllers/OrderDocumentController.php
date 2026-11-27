<?php

namespace App\Http\Controllers;

use App\Listeners\IssueInvoiceOnConfirmation;
use App\Models\Order;
use App\Support\Pdf\OrderDocuments;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Order PDFs. Staff (orders.view): the invoice and the shipping label of any order.
 * Customers: the invoice of their own order, once it has been issued.
 */
class OrderDocumentController extends Controller
{
    public function invoice(Order $order): Response
    {
        $this->authorize('view', $order);
        $invoice = $order->invoice()->firstOrFail(); // not issued yet → 404

        return $this->pdf(OrderDocuments::invoice($order)->output(), $invoice->number);
    }

    /** Company (on-account) orders: the sales order document with signature and stamp boxes. */
    public function salesOrder(Order $order): Response
    {
        $this->authorize('view', $order);
        abort_if($order->company_id === null, 404);

        return $this->pdf(OrderDocuments::salesOrder($order)->output(), $order->number);
    }

    public function shippingLabel(Order $order): Response
    {
        $this->authorize('view', $order);

        return $this->pdf(OrderDocuments::shippingLabel($order)->output(), 'label-'.$order->number);
    }

    public function customerInvoice(Request $request, string $number): Response
    {
        $order = $request->user()->orders()->where('number', $number)->with('invoice')->firstOrFail();
        abort_if($order->invoice === null, 404);

        return $this->pdf(OrderDocuments::invoice($order)->output(), $order->invoice->number, download: true);
    }

    /** Bulk print from the orders list: one label page per selected order (?ids=1,2,3). */
    public function shippingLabels(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->query('ids')))), 0, 200);
        $orders = Order::query()->whereKey($ids)->orderBy('placed_at')->get();
        abort_if($orders->isEmpty(), 404);

        return $this->pdf(OrderDocuments::shippingLabels($orders)->output(), 'labels-'.now()->format('Ymd-His'));
    }

    /** Whether an order of this status already has (or is due) an invoice. */
    public static function issued(Order $order): bool
    {
        return in_array($order->status, IssueInvoiceOnConfirmation::ISSUING_STATUSES, true) || $order->invoice()->exists();
    }

    private function pdf(string $bytes, string $name, bool $download = false): Response
    {
        $file = preg_replace('/[^A-Za-z0-9\-_]/', '', $name).'.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$file.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
