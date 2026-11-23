<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Customer account area: overview, order history, order details, profile. Customers only see their own data. */
class AccountController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Storefront/Account/Dashboard', [
            'profile' => ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone],
            'stats' => [
                'orders' => $user->orders()->count(),
                'open' => $user->orders()->whereIn('status', ['pending', 'confirmed', 'processing', 'shipped'])->count(),
                'spent' => Money::format((int) $user->orders()->where('status', '!=', 'cancelled')->sum('total')),
            ],
            'recentOrders' => $user->orders()->withCount('items')->latest('placed_at')->limit(5)->get()->map($this->summary(...)),
            'seo' => SeoData::make(__('account.my_account'))->noindex(),
        ]);
    }

    public function orders(Request $request): Response
    {
        return Inertia::render('Storefront/Account/Orders', [
            'orders' => $request->user()->orders()->withCount('items')->latest('placed_at')->paginate(15)->through($this->summary(...)),
            'seo' => SeoData::make(__('account.my_orders'))->noindex(),
        ]);
    }

    public function order(Request $request, string $number): Response
    {
        // Scoped lookup: another customer's order number simply 404s (no enumeration).
        $order = $request->user()->orders()->where('number', $number)->with('items')->firstOrFail();

        return Inertia::render('Storefront/Account/OrderShow', [
            'order' => [
                ...$this->summary($order),
                'shipping_city' => $order->shipping_city,
                'shipping_address' => $order->shipping_address,
                'shipping_method' => $order->shipping_method_name,
                'customer_note' => $order->customer_note,
                'subtotal' => Money::format($order->subtotal, $order->currency),
                'shipping_total' => Money::format($order->shipping_total, $order->currency),
                'items' => $order->items->map(fn (OrderItem $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'variant_label' => $i->variant_label,
                    'quantity' => $i->quantity,
                    'unit_price' => Money::format($i->unit_price, $order->currency),
                    'line_total' => Money::format($i->line_total, $order->currency),
                ]),
            ],
            'seo' => SeoData::make(__('account.order', ['number' => $order->number]))->noindex(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
        ]);

        $user->update([
            'name' => trim($data['name']),
            'email' => strtolower($data['email']),
            'phone' => preg_replace('/[\s\-]/', '', $data['phone']),
        ]);

        return back()->with('success', __('account.profile_saved'));
    }

    private function summary(Order $order): array
    {
        return [
            'number' => $order->number,
            'status' => $order->status->value,
            'total' => Money::format($order->total, $order->currency),
            'items_count' => $order->items_count ?? $order->items->count(),
            'date' => $order->placed_at->translatedFormat('j F Y'),
            'url' => route('account.orders.show', $order->number),
        ];
    }
}
