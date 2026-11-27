<?php

namespace App\Queries;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\CompanyPayment;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * A company's account statement: on-account orders (debit, in the base currency) and payments (credit),
 * oldest first with a running balance. Cancelled orders stay listed at 0 so the history is complete.
 * Shared by the admin screen and the printed statement.
 *
 * @phpstan-type Entry array{type: 'order'|'payment', id: int, date: Carbon, reference: ?string, detail: ?string,
 *   note: ?string, status: ?string, debit: int, credit: int, balance: int}
 */
class CompanyStatement
{
    /** @return list<Entry> */
    public function entries(Company $company): array
    {
        $entries = [];

        $orders = $company->orders()->where('payment_method', PaymentMethod::OnAccount->value)->with('salesRep:id,name')->get();
        foreach ($orders as $order) {
            /** @var Order $order */
            $entries[] = [
                'type' => 'order',
                'id' => $order->id,
                'date' => $order->placed_at,
                'reference' => $order->number,
                'detail' => $order->salesRep?->getAttribute('name'),
                'note' => null,
                'status' => $order->status->value,
                'debit' => $order->status === OrderStatus::Cancelled ? 0 : $order->base_total,
                'credit' => 0,
                'balance' => 0,
            ];
        }

        foreach ($company->payments()->get() as $payment) {
            /** @var CompanyPayment $payment */
            $entries[] = [
                'type' => 'payment',
                'id' => $payment->id,
                'date' => $payment->paid_at,
                'reference' => $payment->reference,
                'detail' => $payment->method->value,
                'note' => $payment->note,
                'status' => null,
                'debit' => 0,
                'credit' => $payment->amount,
                'balance' => 0,
            ];
        }

        usort($entries, fn (array $a, array $b) => [$a['date'], $a['type']] <=> [$b['date'], $b['type']]);

        $running = 0;
        foreach ($entries as $i => $entry) {
            $running += $entry['debit'] - $entry['credit'];
            $entries[$i]['balance'] = $running;
        }

        return $entries;
    }
}
