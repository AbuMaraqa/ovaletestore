<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PriceTier;
use App\Support\DataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/** Trader price lists: name, automatic % off, active flag. Exact prices are set per product variant. */
class PriceTierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('price_tiers.view');

        $tiers = QueryBuilder::for(PriceTier::query()->withCount(['traders', 'prices']))
            ->allowedFilters(...[
                AllowedFilter::callback('search', fn (Builder $q, $term) => $q->where(fn (Builder $w) => $w
                    ->where('code', 'like', DataTable::like($term))->orWhere('name', 'like', DataTable::like($term)))),
                AllowedFilter::callback('status', fn (Builder $q, $v) => $q->where('is_active', $v === 'active')),
            ])
            ->allowedSorts(...['sort_order', 'discount_percent', 'traders_count', 'prices_count'])
            ->defaultSort('sort_order')
            ->paginate(DataTable::perPage($request))
            ->withQueryString();

        $user = $request->user();

        return Inertia::render('Admin/PriceTiers/Index', [
            'tiers' => $tiers->through(fn (PriceTier $tier) => [
                'id' => $tier->id,
                'code' => $tier->code,
                'name' => $tier->label(),
                'discount_percent' => (float) $tier->discount_percent,
                'is_active' => $tier->is_active,
                'traders_count' => (int) $tier->getAttribute('traders_count'),
                'prices_count' => (int) $tier->getAttribute('prices_count'),
                'edit_url' => $user->can('price_tiers.update') ? route('admin.price-tiers.edit', $tier) : null,
                'delete_url' => $user->can('price_tiers.delete') ? route('admin.price-tiers.destroy', $tier) : null,
            ]),
            'state' => DataTable::state($request),
        ]);
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
