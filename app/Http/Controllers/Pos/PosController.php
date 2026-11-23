<?php

namespace App\Http\Controllers\Pos;

use App\Actions\Orders\PlacePosOrder;
use App\Enums\OrderChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosOrderRequest;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Queries\PosCatalog;
use App\Support\Money;
use App\Support\Seo\SeoData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** iPad POS for sales reps (route group: auth + can:access-pos). */
class PosController extends Controller
{
    /** Pick a company + the rep's latest orders. */
    public function index(Request $request): Response
    {
        return Inertia::render('Pos/Index', [
            'companies' => Company::query()->active()->orderBy('name')
                ->get(['id', 'name', 'contact_name', 'phone', 'city'])
                ->map(fn (Company $c) => $c->only(['id', 'name', 'contact_name', 'phone', 'city'])),
            'recent' => $this->repOrders($request)->limit(6)->get()->map(fn (Order $o) => $this->orderRow($o)),
            'seo' => SeoData::make(__('pos.title'))->noindex(),
        ]);
    }

    /** Order screen for one company. */
    public function create(Company $company, PosCatalog $catalog): Response
    {
        abort_unless($company->is_active, 404);

        return Inertia::render('Pos/Order', [
            'company' => $company->only(['id', 'name', 'contact_name', 'phone', 'city', 'address']),
            'catalog' => $catalog->get(),
            'seo' => SeoData::make(__('pos.new_order').' — '.$company->name)->noindex(),
        ]);
    }

    public function store(PosOrderRequest $request, PlacePosOrder $placeOrder): RedirectResponse
    {
        $company = Company::query()->findOrFail($request->integer('company_id'));
        $order = $placeOrder->handle($company, $request->user(), $request->quantities(), $request->validated('note'));

        return to_route('pos.orders.show', $order)->with('success', __('pos.order_sent', ['number' => $order->number]));
    }

    public function orders(Request $request): Response
    {
        return Inertia::render('Pos/Orders', [
            'orders' => $this->repOrders($request)->paginate(30)->through(fn (Order $o) => $this->orderRow($o)),
            'seo' => SeoData::make(__('pos.my_orders'))->noindex(),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        // Reps see their own POS orders; order staff may open any.
        abort_unless($order->channel === OrderChannel::Pos
            && ($order->sales_rep_id === $request->user()->id || $request->user()->can('orders.view')), 404);

        $order->load('items');

        return Inertia::render('Pos/Receipt', [
            'order' => [
                ...$this->orderRow($order),
                'note' => $order->customer_note,
                'items' => $order->items->map(fn (OrderItem $i) => [
                    'name' => $i->name,
                    'variant' => $i->variant_label,
                    'sku' => $i->sku,
                    'quantity' => $i->quantity,
                    'unit_price' => Money::format($i->unit_price, $order->currency),
                    'line_total' => Money::format($i->line_total, $order->currency),
                ]),
            ],
            'seo' => SeoData::make($order->number)->noindex(),
        ]);
    }

    /** @return Builder<Order> */
    private function repOrders(Request $request)
    {
        return Order::query()->where('channel', OrderChannel::Pos->value)
            ->where('sales_rep_id', $request->user()->id)
            ->withCount('items')->latest('placed_at');
    }

    /** @return array<string, mixed> */
    private function orderRow(Order $o): array
    {
        return [
            'id' => $o->id,
            'number' => $o->number,
            'company' => $o->customer_name,
            'status' => $o->status->value,
            'items_count' => $o->items_count ?? $o->items()->count(),
            'total' => Money::format($o->total, $o->currency),
            'date' => $o->placed_at->format('Y-m-d H:i'),
        ];
    }
}
