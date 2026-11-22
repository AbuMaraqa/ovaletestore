<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
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

        $orders = QueryBuilder::for(Order::class)
            ->withCount('items')
            ->allowedFilters(...[
                AllowedFilter::exact('status'),
                AllowedFilter::callback('search', function (Builder $q, $term) {
                    $like = '%'.addcslashes((string) $term, '%_\\').'%';
                    $q->where(fn ($w) => $w->where('number', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('customer_phone', 'like', $like));
                }),
            ])
            ->allowedSorts(...['placed_at', 'total'])
            ->defaultSort('-placed_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders->through(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'customer' => $o->customer_name,
                'phone' => $o->customer_phone,
                'city' => $o->shipping_city,
                'status' => $o->status->value,
                'items_count' => $o->items_count,
                'total' => Money::format($o->total, $o->currency),
                'date' => $o->placed_at->format('Y-m-d H:i'),
            ]),
            'filters' => $request->only(['filter']),
            'statuses' => array_column(OrderStatus::cases(), 'value'),
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);
        $order->load(['items', 'notes.author']);

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status->value,
                'allowed_statuses' => array_map(fn (OrderStatus $s) => $s->value, $order->status->allowedTransitions()),
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'customer_email' => $order->customer_email,
                'shipping_city' => $order->shipping_city,
                'shipping_address' => $order->shipping_address,
                'customer_note' => $order->customer_note,
                'subtotal' => Money::format($order->subtotal, $order->currency),
                'shipping_total' => Money::format($order->shipping_total, $order->currency),
                'total' => Money::format($order->total, $order->currency),
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
