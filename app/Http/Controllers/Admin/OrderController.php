<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\ChangeOrderStatus;
use App\Actions\Orders\DeleteOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;
use App\Tables\Admin\OrdersTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        return Inertia::render('Admin/Orders/Index', (new OrdersTable($request))->props('orders'));
    }

    /**
     * Removes the order and its items/notes. Goods that were reserved but never left (not completed,
     * shipped or already cancelled) go back to stock first, exactly like a cancellation.
     */
    public function destroy(Order $order, DeleteOrder $delete): RedirectResponse
    {
        $this->authorize('delete', $order);
        $delete->handle($order);

        return to_route('admin.orders.index')->with('success', __('admin.deleted'));
    }

    public function show(Request $request, Order $order): Response
    {
        $this->authorize('view', $order);
        $order->load(['items', 'notes.author', 'salesRep:id,name', 'user:id,name']);
        $user = $request->user();

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status->value,
                'allowed_statuses' => $user->can('update', $order)
                    ? array_map(fn (OrderStatus $s) => $s->value, $order->status->allowedTransitions()) : [],
                'customer_account' => $order->user && $user->can('customers.view')
                    ? ['name' => $order->user->name, 'url' => route('admin.customers.show', $order->user)] : null,
                'delete_url' => $user->can('delete', $order) ? route('admin.orders.destroy', $order) : null,
                'invoice' => $order->invoice ? ['number' => $order->invoice->number, 'url' => route('admin.orders.invoice', $order)] : null,
                'label_url' => route('admin.orders.label', $order),
                'sales_order_url' => $order->company_id ? route('admin.orders.sales-order', $order) : null,
                'status_flow' => array_map(fn (OrderStatus $s) => $s->value, OrderStatus::FLOW),
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'customer_email' => $order->customer_email,
                'shipping_city' => $order->shipping_city,
                'shipping_address' => $order->shipping_address,
                'shipping_method' => $order->shipping_method_name,
                'channel' => $order->channel->value,
                'payment_method' => $order->payment_method->value,
                'company_id' => $order->company_id,
                'sales_rep' => $order->salesRep?->getAttribute('name'),
                'customer_note' => $order->customer_note,
                'subtotal' => Money::format($order->subtotal, $order->currency),
                'shipping_total' => Money::format($order->shipping_total, $order->currency),
                'total' => Money::format($order->total, $order->currency),
                // Paid in a non-base currency: show the rate used and the base amount reports count.
                'foreign_currency' => $order->currency !== Money::storeCurrency() ? [
                    'note' => __('currencies.paid_in', ['code' => $order->currency, 'rate' => rtrim(rtrim((string) $order->exchange_rate, '0'), '.')]),
                    'base_total' => Money::format($order->base_total),
                ] : null,
                'placed_at' => $order->placed_at->format('Y-m-d H:i'),
                'items' => $order->items->map(fn (OrderItem $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'variant_label' => $i->variant_label,
                    'sku' => $i->sku,
                    'quantity' => $i->quantity,
                    'unit_price' => Money::format($i->unit_price, $order->currency),
                    'line_total' => Money::format($i->line_total, $order->currency),
                ]),
                'notes' => $order->notes->map(fn ($n) => [
                    'id' => $n->id,
                    'body' => $n->body,
                    'author' => $n->author?->name,
                    'date' => $n->created_at->diffForHumans(),
                ]),
            ],
        ]);
    }

    public function updateStatus(Request $request, Order $order, ChangeOrderStatus $change): RedirectResponse
    {
        $this->authorize('update', $order);

        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $change->handle($order, OrderStatus::from($data['status']), $request->user(), $data['note'] ?? null);

        return back()->with('success', __('admin.updated'));
    }
}
