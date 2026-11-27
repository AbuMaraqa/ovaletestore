<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Traders\ReviewTraderRequest;
use App\Enums\TraderStatus;
use App\Http\Controllers\Controller;
use App\Models\PriceTier;
use App\Models\User;
use App\Tables\Admin\TradersTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Trader accounts: requests waiting for approval, approved traders and their price tier. */
class TraderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('traders.view');

        return Inertia::render('Admin/Traders/Index', (new TradersTable($request))->props('traders') + [
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
