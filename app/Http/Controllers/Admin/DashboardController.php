<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $canSeeOrders = $request->user()->can('orders.view');

        return Inertia::render('Admin/Dashboard/Index', [
            'stats' => [
                'products' => Product::query()->published()->count(),
                'orders_today' => $canSeeOrders ? Order::query()->where('placed_at', '>=', today())->count() : null,
                'revenue_month' => $canSeeOrders ? Money::format((int) Order::query()
                    ->where('placed_at', '>=', now()->startOfMonth())
                    ->where('status', '!=', OrderStatus::Cancelled)
                    ->sum('total')) : null,
                'pending' => $canSeeOrders ? Order::query()->where('status', OrderStatus::Pending)->count() : null,
            ],
            'recentOrders' => $canSeeOrders
                ? Order::query()->latest('placed_at')->limit(8)->get()->map(fn (Order $o) => [
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
}
