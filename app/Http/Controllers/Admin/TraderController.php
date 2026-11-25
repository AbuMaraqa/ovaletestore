<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Traders\ReviewTraderRequest;
use App\Enums\TraderStatus;
use App\Http\Controllers\Controller;
use App\Models\PriceTier;
use App\Models\User;
use App\Support\DataTable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/** Trader accounts: requests waiting for approval, approved traders and their price tier. */
class TraderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('traders.view');

        $traders = QueryBuilder::for(User::query()->whereNotNull('trader_status')->with(['media', 'priceTier'])
            ->withCount('orders')->withSum('orders as spent', 'base_total'))
            ->allowedFilters(...[
                AllowedFilter::callback('search', function (Builder $q, $term) {
                    $like = DataTable::like($term);
                    $q->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('business_name', 'like', $like)
                        ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
                }),
                AllowedFilter::exact('status', 'trader_status'),
                AllowedFilter::exact('tier', 'price_tier_id'),
            ])
            ->allowedSorts(...['name', 'business_name', 'trader_requested_at', 'orders_count', AllowedSort::field('spent')])
            ->defaultSort('-trader_requested_at')
            ->paginate(DataTable::perPage($request))
            ->withQueryString();

        $canApprove = $request->user()->can('traders.approve');

        return Inertia::render('Admin/Traders/Index', [
            'traders' => $traders->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'business_name' => $u->business_name,
                'email' => $u->email,
                'phone' => $u->phone,
                'avatar' => $u->avatar(),
                'status' => $u->trader_status?->value,
                'tier_id' => $u->price_tier_id,
                'tier' => $u->priceTier?->label(),
                'orders_count' => (int) $u->getAttribute('orders_count'),
                'spent' => Money::format((int) $u->getAttribute('spent')),
                'requested' => $u->trader_requested_at?->diffForHumans(),
                'show_url' => $request->user()->can('customers.view') ? route('admin.customers.show', $u) : null,
                'can_review' => $canApprove,
            ]),
            'state' => DataTable::state($request),
            'tiers' => $this->tierOptions(),
            'pending' => User::query()->where('trader_status', TraderStatus::Pending)->count(),
        ]);
    }

    /** Approve (with a tier), move to another tier, or reject a trader request. */
    public function review(Request $request, User $trader, ReviewTraderRequest $review): RedirectResponse
    {
        $this->authorize('traders.approve');
        // Customers only (staff never get trader prices); a customer without a request can be made a trader directly.
        abort_unless($trader->roles()->pluck('name')->all() === ['customer'], 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TraderStatus::class)->except([TraderStatus::Pending])],
            'price_tier_id' => ['required_if:status,approved', 'nullable', Rule::exists('price_tiers', 'id')],
        ]);

        $review->handle($trader, TraderStatus::from($data['status']), isset($data['price_tier_id']) ? PriceTier::query()->find($data['price_tier_id']) : null);

        return back()->with('success', __('admin.updated'));
    }

    /** @return list<array{value: int, label: string}> */
    private function tierOptions(): array
    {
        return PriceTier::query()->orderBy('sort_order')->get()
            ->map(fn (PriceTier $t) => ['value' => $t->id, 'label' => $t->label().($t->is_active ? '' : ' ('.__('admin.inactive').')')])
            ->values()->all();
    }
}
