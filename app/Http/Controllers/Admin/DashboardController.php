<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Support\Analytics\StoreVisits;
use App\Support\CurrencyContext;
use App\Support\Money;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $canSeeOrders = $request->user()->can('orders.view');

        return Inertia::render('Admin/Dashboard/Index', [
            'kpis' => $canSeeOrders ? $this->kpis() : null,
            'chart' => $canSeeOrders ? Inertia::defer(fn () => $this->revenueChart()) : null,
            // Traffic card: the store's own counter (StoreVisits) — instant and always on; Google Analytics has its own
            // tab on the Site traffic page. Deferred.
            'traffic' => $request->user()->can('analytics.view')
                ? Inertia::defer(fn () => app(StoreVisits::class)->summary(), 'traffic')
                : null,
            'currencySymbol' => app(CurrencyContext::class)->base()->symbol(),
            'catalog' => $this->catalogHealth(),
            'topCategories' => Category::query()->with('translations')
                ->withCount(['products' => fn ($q) => $q->published()])
                ->orderByDesc('products_count')->limit(6)->get()
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'count' => $c->products_count]),
            'recentOrders' => $canSeeOrders
                ? Order::query()->latest('placed_at')->limit(6)->get()->map(fn (Order $o) => [
                    'id' => $o->id,
                    'number' => $o->number,
                    'customer' => $o->customer_name,
                    'status' => $o->status->value,
                    'total' => Money::format($o->total, $o->currency),
                    'date' => $o->placed_at->diffForHumans(),
                ])
                : [],
        ]);
    }

    /** Month-to-date vs the same period last month. */
    private function kpis(): array
    {
        $valid = fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled);
        $thisStart = now()->startOfMonth();
        $lastStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastEnd = now()->subMonthNoOverflow();

        $revenueNow = (int) Order::query()->tap($valid)->where('placed_at', '>=', $thisStart)->sum('base_total');
        $revenueLast = (int) Order::query()->tap($valid)->whereBetween('placed_at', [$lastStart, $lastEnd])->sum('base_total');
        $ordersNow = Order::query()->tap($valid)->where('placed_at', '>=', $thisStart)->count();
        $ordersLast = Order::query()->tap($valid)->whereBetween('placed_at', [$lastStart, $lastEnd])->count();

        return [
            'revenue' => ['value' => Money::format($revenueNow), 'change' => $this->change($revenueNow, $revenueLast)],
            'orders' => ['value' => $ordersNow, 'change' => $this->change($ordersNow, $ordersLast)],
            'average' => ['value' => Money::format($ordersNow ? intdiv($revenueNow, $ordersNow) : 0), 'change' => null],
            'pending' => ['value' => Order::query()->where('status', OrderStatus::Pending)->count(), 'change' => null],
        ];
    }

    private function change(int $now, int $before): ?float
    {
        return $before === 0 ? null : round(($now - $before) / $before * 100, 1);
    }

    /** Daily revenue (major units) + order count for the last 30 days, zero-filled. */
    private function revenueChart(): array
    {
        $from = now()->subDays(29)->startOfDay();

        $rows = Order::query()
            ->where('status', '!=', OrderStatus::Cancelled)
            ->where('placed_at', '>=', $from)
            ->selectRaw('DATE(placed_at) as day, SUM(base_total) as revenue, COUNT(*) as orders')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        return collect(CarbonPeriod::create($from, now()))->map(fn ($date) => [
            'date' => $date->toDateString(),
            'label' => $date->translatedFormat('d M'),
            'revenue' => round(((int) ($rows[$date->toDateString()]->revenue ?? 0)) / 100, 2),
            'orders' => (int) ($rows[$date->toDateString()]->orders ?? 0),
        ])->values()->all();
    }

    /** Post-migration data quality, so editors know what to fix. */
    private function catalogHealth(): array
    {
        $published = Product::query()->published();
        $productMorph = (new Product)->getMorphClass();

        return [
            'published' => (clone $published)->count(),
            'drafts' => Product::query()->where('status', 'draft')->count(),
            'without_image' => (clone $published)->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('media')
                ->whereColumn('media.model_id', 'products.id')->where('media.model_type', $productMorph))->count(),
            'without_english' => (clone $published)->whereDoesntHave('translations', fn ($q) => $q->where('locale', 'en'))->count(),
            'without_hebrew' => (clone $published)->whereDoesntHave('translations', fn ($q) => $q->where('locale', 'he'))->count(),
            'without_sku' => ProductVariant::query()->whereNull('sku')->count(),
            'out_of_stock' => ProductVariant::query()->where('stock_status', 'out_of_stock')->count(),
            'on_sale' => ProductVariant::query()->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'regular_price')->count(),
            'english_ratio' => ($total = (clone $published)->count())
                ? round(ProductTranslation::query()->where('locale', 'en')->count() / $total * 100)
                : 0,
        ];
    }
}
