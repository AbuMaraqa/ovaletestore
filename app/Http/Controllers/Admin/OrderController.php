<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\ChangeOrderStatus;
use App\Actions\Orders\DeleteOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingMethod;
use App\Support\DataTable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $orders = QueryBuilder::for(Order::query()->with('user:id,name'))
            ->withCount('items')
            ->allowedFilters(...[
                AllowedFilter::exact('status'),
                AllowedFilter::exact('channel'),
                AllowedFilter::exact('payment', 'payment_method'),
                AllowedFilter::exact('shipping', 'shipping_method_id'),
                AllowedFilter::exact('company_id'),
                AllowedFilter::exact('customer', 'user_id'),
                AllowedFilter::callback('account', fn (Builder $q, $v) => $v === 'registered' ? $q->whereNotNull('user_id') : $q->whereNull('user_id')),
                AllowedFilter::callback('search', function (Builder $q, $term) {
                    $like = DataTable::like($term);
                    $q->where(fn ($w) => $w->where('number', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('customer_phone', 'like', $like)
                        ->orWhere('customer_email', 'like', $like)
                        ->orWhere('shipping_city', 'like', $like));
                }),
                ...DataTable::dateRange('placed_at'),
            ])
            ->allowedSorts(...['placed_at', 'total', 'number', 'customer_name', 'status', 'items_count'])
            ->defaultSort('-placed_at')
            ->paginate(DataTable::perPage($request))
            ->withQueryString();

        $canDelete = $request->user()->can('orders.delete');
        $canSeeCustomers = $request->user()->can('customers.view');

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders->through(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'customer' => $o->customer_name,
                'customer_url' => $o->user && $canSeeCustomers ? route('admin.customers.show', $o->user) : null,
                'phone' => $o->customer_phone,
                'email' => $o->customer_email,
                'city' => $o->shipping_city,
                'shipping' => $o->shipping_method_name,
                'payment' => $o->payment_method->value,
                'status' => $o->status->value,
                'channel' => $o->channel->value,
                'items_count' => $o->items_count,
                'total' => Money::format($o->total, $o->currency),
                'date' => $o->placed_at->format('Y-m-d H:i'),
                'ago' => $o->placed_at->diffForHumans(),
                'show_url' => route('admin.orders.show', $o),
                'delete_url' => $canDelete ? route('admin.orders.destroy', $o) : null,
            ]),
            'state' => DataTable::state($request),
            'statuses' => array_column(OrderStatus::cases(), 'value'),
            'shipping_methods' => ShippingMethod::query()->with('translations')->orderBy('sort_order')->get()
                ->map(fn (ShippingMethod $m) => ['value' => $m->id, 'label' => $m->name]),
        ]);
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
