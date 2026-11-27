<?php

namespace App\Http\Controllers\Pos;

use App\Actions\Orders\PlacePosOrder;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosOrderRequest;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Queries\PosCatalog;
use App\Support\Money;
use App\Support\Pdf\OrderDocuments;
use App\Support\Seo\SeoData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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
            'can' => ['create_company' => $request->user()->can('pos.companies.create'), 'edit_company' => $request->user()->can('pos.companies.update')],
            'seo' => SeoData::make(__('pos.title'))->noindex(),
        ]);
    }

    /** Order screen for one company. */
    public function create(Request $request, Company $company, PosCatalog $catalog): Response
    {
        abort_unless($company->is_active, 404);

        return Inertia::render('Pos/Order', [
            'company' => $company->only(['id', 'name', 'contact_name', 'phone', 'city', 'address']),
            'can_edit_company' => $request->user()->can('pos.companies.update'),
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

    /** The rep's orders: search by order number, company or phone; filter by status and date range. */
    public function orders(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $orders = $this->repOrders($request)
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $like = '%'.addcslashes(trim($term), '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('number', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('placed_at', '>=', $from))
            ->when($filters['until'] ?? null, fn (Builder $q, string $until) => $q->whereDate('placed_at', '<=', $until))
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Pos/Orders', [
            'orders' => $orders->through(fn (Order $o) => $this->orderRow($o)),
            'filters' => [
                'q' => $filters['q'] ?? '', 'status' => $filters['status'] ?? '',
                'from' => $filters['from'] ?? '', 'until' => $filters['until'] ?? '',
            ],
            'statuses' => array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()),
            'seo' => SeoData::make(__('pos.my_orders'))->noindex(),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->ensureVisible($request, $order);

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
            'pdf_url' => route('pos.orders.pdf', $order),
            'seo' => SeoData::make($order->number)->noindex(),
        ]);
    }

    /** The company's sales order as a PDF (mPDF): what was ordered, with signature and stamp boxes. */
    public function pdf(Request $request, Order $order): HttpResponse
    {
        $this->ensureVisible($request, $order);

        return response(OrderDocuments::salesOrder($order)->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.preg_replace('/[^A-Za-z0-9\-]/', '', $order->number).'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Reps see their own POS orders; order staff may open any. */
    private function ensureVisible(Request $request, Order $order): void
    {
        abort_unless($order->channel === OrderChannel::Pos
            && ($order->sales_rep_id === $request->user()->id || $request->user()->can('orders.view')), 404);
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
