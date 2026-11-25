<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShippingMethodType;
use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Support\DataTable;
use App\Support\Money;
use App\Support\TranslationInput;
use Astrotomic\Translatable\Validation\RuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/** Shipping methods (WooCommerce → Settings → Shipping). Prices are entered in major units, stored in minor units. */
class ShippingMethodController extends Controller
{
    private const FIELDS = ['name', 'description'];

    public function index(Request $request): Response
    {
        $this->authorize('shipping.view');

        $methods = QueryBuilder::for(ShippingMethod::query()->with('translations')->withCount('orders'))
            ->allowedFilters(...[
                AllowedFilter::callback('search', function (Builder $q, $term) {
                    $like = DataTable::like($term);
                    $q->where(fn ($w) => $w->where('code', 'like', $like)->orWhereHas('translations', fn ($t) => $t->where('name', 'like', $like)));
                }),
                AllowedFilter::exact('type'),
                AllowedFilter::callback('status', fn (Builder $q, $v) => $q->where('is_active', $v === 'active')),
            ])
            ->allowedSorts(...['sort_order', 'cost', 'orders_count', 'id'])
            ->defaultSort('sort_order')
            ->paginate(DataTable::perPage($request))
            ->withQueryString();

        $user = $request->user();

        return Inertia::render('Admin/ShippingMethods/Index', [
            'methods' => $methods->through(fn (ShippingMethod $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'type' => $m->type->value,
                'cost' => $m->cost > 0 ? Money::format($m->cost) : null,
                'free_over' => $m->free_over !== null ? Money::format($m->free_over) : null,
                'min_order' => $m->min_order !== null ? Money::format($m->min_order) : null,
                'is_active' => $m->is_active,
                'orders_count' => $m->orders_count,
                'edit_url' => $user->can('shipping.update') ? route('admin.shipping-methods.edit', $m) : null,
                'delete_url' => $user->can('shipping.delete') ? route('admin.shipping-methods.destroy', $m) : null,
            ]),
            'state' => DataTable::state($request),
            'types' => array_column(ShippingMethodType::cases(), 'value'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('shipping.create');

        return Inertia::render('Admin/ShippingMethods/Form', ['method' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('shipping.create');
        $this->save($request, new ShippingMethod);

        return to_route('admin.shipping-methods.index')->with('success', __('admin.created'));
    }

    public function edit(ShippingMethod $shippingMethod): Response
    {
        $this->authorize('shipping.update');
        $shippingMethod->load('translations');

        return Inertia::render('Admin/ShippingMethods/Form', ['method' => [
            'id' => $shippingMethod->id,
            'type' => $shippingMethod->type->value,
            'cost' => Money::toMajor($shippingMethod->cost),
            'free_over' => $shippingMethod->free_over !== null ? Money::toMajor($shippingMethod->free_over) : null,
            'min_order' => $shippingMethod->min_order !== null ? Money::toMajor($shippingMethod->min_order) : null,
            'is_active' => $shippingMethod->is_active,
            'sort_order' => $shippingMethod->sort_order,
            'translations' => collect(config('translatable.locales'))->mapWithKeys(fn ($l) => [
                $l => collect(self::FIELDS)->mapWithKeys(fn ($f) => [
                    $f => $shippingMethod->hasTranslation($l) ? (string) $shippingMethod->translate($l)->getAttribute($f) : '',
                ]),
            ]),
        ]]);
    }

    public function update(Request $request, ShippingMethod $shippingMethod): RedirectResponse
    {
        $this->authorize('shipping.update');
        $this->save($request, $shippingMethod);

        return to_route('admin.shipping-methods.index')->with('success', __('admin.updated'));
    }

    /** Past orders keep their snapshot name; the FK is nulled. */
    public function destroy(ShippingMethod $shippingMethod): RedirectResponse
    {
        $this->authorize('shipping.delete');
        $shippingMethod->delete();

        return to_route('admin.shipping-methods.index')->with('success', __('admin.deleted'));
    }

    private function save(Request $request, ShippingMethod $method): void
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(ShippingMethodType::class)],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'free_over' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'min_order' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            ...RuleFactory::make([
                'translations.%name%' => ['nullable', 'string', 'max:120'],
                'translations.%description%' => ['nullable', 'string', 'max:255'],
            ]),
            'translations.ar.name' => ['required', 'string', 'max:120'],
        ]);

        $minor = fn (mixed $v): ?int => $v === null || $v === '' ? null : (int) Money::toMinor($v);

        DB::transaction(function () use ($method, $data, $minor) {
            $method->fill([
                'type' => $data['type'],
                'cost' => $minor($data['cost'] ?? null) ?? 0,
                'free_over' => $minor($data['free_over'] ?? null),
                'min_order' => $minor($data['min_order'] ?? null),
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
            TranslationInput::apply($method, $data['translations'], self::FIELDS);
            $method->save();
        });
    }
}
