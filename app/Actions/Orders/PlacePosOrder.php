<?php

namespace App\Actions\Orders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\OrderPlaced;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A sales rep's order for a company from the POS: website prices, no shipping charge,
 * added to the company's account (payment_method = on_account). Same stock rules as checkout.
 */
class PlacePosOrder
{
    public function __construct(
        private readonly BuildOrderLines $buildLines,
    ) {}

    /** @param array<int, int> $quantities variant id => quantity */
    public function handle(Company $company, User $rep, array $quantities, ?string $note = null): Order
    {
        if (! $company->is_active) {
            throw ValidationException::withMessages(['company_id' => __('pos.company_inactive')]);
        }

        return DB::transaction(function () use ($company, $rep, $quantities, $note): Order {
            $lines = $this->buildLines->handle($quantities, 'items');
            $subtotal = array_sum(array_column($lines, 'line_total'));

            $order = Order::create([
                'number' => BuildOrderLines::generateNumber('POS'),
                'company_id' => $company->id,
                'sales_rep_id' => $rep->id,
                'status' => OrderStatus::Pending,
                'channel' => OrderChannel::Pos,
                'payment_method' => PaymentMethod::OnAccount,
                // Snapshot of the company at order time (the company record may change later).
                'customer_name' => $company->name,
                'customer_phone' => $company->phone,
                'shipping_city' => $company->city,
                'shipping_address' => $company->address,
                'customer_note' => $note,
                'currency' => Money::storeCurrency(), // POS sells in the base currency (company balances are in it)
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'shipping_total' => 0,
                'total' => $subtotal,
                'locale' => app()->getLocale(),
                'placed_at' => now(),
            ]);

            $order->items()->createMany($lines);
            OrderPlaced::dispatch($order);

            return $order;
        });
    }
}
