<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PriceTier;
use App\Tables\Admin\PriceTiersTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Trader price lists: name, automatic % off, active flag. Exact prices are set per product variant. */
class PriceTierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('price_tiers.view');

        return Inertia::render('Admin/PriceTiers/Index', (new PriceTiersTable($request))->props('tiers'));
    }

    public function create(): Response
    {
        $this->authorize('price_tiers.create');

        return Inertia::render('Admin/PriceTiers/Form', ['tier' => null, 'locales' => config('translatable.locales')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('price_tiers.create');
        $tier = PriceTier::query()->create($this->validated($request));

        return to_route('admin.price-tiers.edit', $tier)->with('success', __('admin.created'));
    }

    public function edit(PriceTier $priceTier): Response
    {
        $this->authorize('price_tiers.view');

        return Inertia::render('Admin/PriceTiers/Form', [
            'tier' => [
                'id' => $priceTier->id,
                'code' => $priceTier->code,
                'name' => $priceTier->name,
                'discount_percent' => (float) $priceTier->discount_percent,
                'is_active' => $priceTier->is_active,
                'sort_order' => $priceTier->sort_order,
                'traders_count' => $priceTier->traders()->count(),
                'prices_count' => $priceTier->prices()->count(),
            ],
            'locales' => config('translatable.locales'),
        ]);
    }

    public function update(Request $request, PriceTier $priceTier): RedirectResponse
    {
        $this->authorize('price_tiers.update');
        $priceTier->update($this->validated($request, $priceTier));

        return back()->with('success', __('admin.updated'));
    }

    /** A tier still assigned to traders cannot go: they would silently fall back to retail prices. */
    public function destroy(PriceTier $priceTier): RedirectResponse
    {
        $this->authorize('price_tiers.delete');
        if ($priceTier->traders()->exists()) {
            return back()->with('error', __('traders.tier_in_use'));
        }
        $priceTier->delete();

        return to_route('admin.price-tiers.index')->with('success', __('admin.deleted'));
    }

    private function validated(Request $request, ?PriceTier $tier = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/', Rule::unique('price_tiers', 'code')->ignore($tier?->id)],
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:80'],
            'name.en' => ['nullable', 'string', 'max:80'],
            'name.he' => ['nullable', 'string', 'max:80'],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:90'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $data['name'] = array_filter($data['name'], fn ($v) => filled($v));
        $data['sort_order'] ??= 0;

        return $data;
    }
}
