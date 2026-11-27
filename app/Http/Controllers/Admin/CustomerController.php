<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\User;
use App\Support\DataTable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Store customers (storefront accounts, incl. those migrated from WooCommerce): their orders and spend.
 * Staff accounts are managed under Users; guest orders stay without an account.
 */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('customers.view');

        $customers = QueryBuilder::for($this->customers()
            ->with('media')
            ->withCount('orders')
            ->withSum(['orders as spent' => fn (Builder $q) => $q->where('status', '!=', OrderStatus::Cancelled)], 'base_total')
            ->withMax('orders as last_order_at', 'placed_at'))
            ->allowedFilters(...[
                AllowedFilter::callback('search', function (Builder $q, $term) {
                    $like = DataTable::like($term);
                    $q->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
                }),
                AllowedFilter::callback('status', fn (Builder $q, $v) => $q->where('is_active', $v === 'active')),
                AllowedFilter::callback('orders', fn (Builder $q, $v) => $v === 'with' ? $q->has('orders') : $q->doesntHave('orders')),
                AllowedFilter::callback('source', fn (Builder $q, $v) => $v === 'woocommerce' ? $q->whereNotNull('source_id') : $q->whereNull('source_id')),
                ...DataTable::dateRange('created_at'),
            ])
            ->allowedSorts(...['name', 'created_at', 'orders_count', AllowedSort::field('spent'), AllowedSort::field('last_order_at')])
            ->defaultSort('-created_at')
            ->paginate(DataTable::perPage($request))
            ->withQueryString();

        $user = $request->user();

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers->through(fn (User $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'avatar' => $c->avatar(),
                'is_active' => $c->is_active,
                'from_woocommerce' => $c->source_id !== null,
                'orders_count' => (int) $c->getAttribute('orders_count'),
                'spent' => Money::format((int) $c->getAttribute('spent')),
                'last_order' => $c->getAttribute('last_order_at') ? now()->parse($c->getAttribute('last_order_at'))->diffForHumans() : null,
                'registered' => $c->created_at?->format('Y-m-d'),
                'show_url' => route('admin.customers.show', $c),
                'edit_url' => $user->can('customers.update') ? route('admin.customers.edit', $c) : null,
                'delete_url' => $user->can('customers.delete') ? route('admin.customers.destroy', $c) : null,
            ]),
            'state' => DataTable::state($request),
            'stats' => [
                'total' => $this->customers()->count(),
                'with_orders' => $this->customers()->has('orders')->count(),
                'new_this_month' => $this->customers()->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
        ]);
    }

    public function show(Request $request, User $customer): Response
    {
        $this->authorize('customers.view');
        $this->ensureCustomer($customer);

        $orders = $customer->orders()->withCount('items')->latest('placed_at')->paginate(15)->withQueryString();
        $valid = $customer->orders()->where('status', '!=', OrderStatus::Cancelled);

        return Inertia::render('Admin/Customers/Show', [
            'customer' => [
                ...$this->profile($customer),
                'edit_url' => $request->user()->can('customers.update') ? route('admin.customers.edit', $customer) : null,
                'delete_url' => $request->user()->can('customers.delete') ? route('admin.customers.destroy', $customer) : null,
                'trader' => [
                    'status' => $customer->trader_status?->value,
                    'business_name' => $customer->business_name,
                    'tier_id' => $customer->price_tier_id,
                    'tier' => $customer->priceTier?->label(),
                    'requested' => $customer->trader_requested_at?->diffForHumans(),
                    'can_review' => $request->user()->can('traders.approve'),
                ],
            ],
            'tiers' => $request->user()->can('traders.approve')
                ? PriceTier::query()->orderBy('sort_order')->get()->map(fn (PriceTier $t) => ['value' => $t->id, 'label' => $t->label()])->all()
                : [],
            'stats' => [
                'orders' => $customer->orders()->count(),
                'spent' => Money::format((int) (clone $valid)->sum('base_total')),
                'average' => Money::format((int) round((float) (clone $valid)->avg('base_total'))),
                'last_order' => $customer->orders()->max('placed_at') ? now()->parse($customer->orders()->max('placed_at'))->diffForHumans() : null,
            ],
            'orders' => $orders->through(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'status' => $o->status->value,
                'items_count' => $o->items_count,
                'total' => Money::format($o->total, $o->currency),
                'date' => $o->placed_at->format('Y-m-d H:i'),
                'show_url' => route('admin.orders.show', $o),
            ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('customers.create');

        return Inertia::render('Admin/Customers/Form', ['customer' => null]);
    }

    /**
     * A customer account created by staff (phone/shop orders). Without a typed password the account gets
     * a random one and, if asked, the customer is emailed a link to choose their own.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('customers.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
            'locale' => ['required', Rule::in(config('translatable.locales'))],
            'is_active' => ['boolean'],
            'password' => ['nullable', 'string', Password::defaults()],
            'send_password_link' => ['boolean'],
        ]);

        $customer = DB::transaction(function () use ($data): User {
            $user = new User([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'locale' => $data['locale'],
                'is_active' => $data['is_active'] ?? true,
                'password' => filled($data['password'] ?? null) ? $data['password'] : Str::password(32),
            ]);
            $user->email_verified_at = now(); // added by staff: the address was given by the customer
            $user->save();
            $user->assignRole('customer');

            return $user;
        });

        if (blank($data['password'] ?? null) && ($data['send_password_link'] ?? false)) {
            PasswordBroker::sendResetLink(['email' => $customer->email]);
        }

        return to_route('admin.customers.show', $customer)->with('success', __('customers.created'));
    }

    public function edit(User $customer): Response
    {
        $this->authorize('customers.update');
        $this->ensureCustomer($customer);

        return Inertia::render('Admin/Customers/Form', ['customer' => $this->profile($customer)]);
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        $this->authorize('customers.update');
        $this->ensureCustomer($customer);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ]);
        $customer->update($data);

        return to_route('admin.customers.show', $customer)->with('success', __('admin.updated'));
    }

    /** The account goes; its orders stay (as guest orders) for the books. */
    public function destroy(User $customer): RedirectResponse
    {
        $this->authorize('customers.delete');
        $this->ensureCustomer($customer);
        $customer->delete();

        return to_route('admin.customers.index')->with('success', __('admin.deleted'));
    }

    /** @return Builder<User> */
    private function customers(): Builder
    {
        return User::query()->role('customer');
    }

    /** Staff accounts are never edited or deleted from here (privileges are managed under Users). */
    private function ensureCustomer(User $user): void
    {
        // By roles, not by the access gates (those are false for a deactivated staff member).
        abort_unless($user->roles()->pluck('name')->all() === ['customer'], 404);
    }

    private function profile(User $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => $c->phone,
            'avatar' => $c->avatar(),
            'is_active' => $c->is_active,
            'from_woocommerce' => $c->source_id !== null,
            'registered' => $c->created_at?->format('Y-m-d'),
            'email_verified' => $c->email_verified_at !== null,
        ];
    }
}
