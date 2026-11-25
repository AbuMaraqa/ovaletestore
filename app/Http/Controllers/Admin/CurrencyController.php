<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Currencies\SetBaseCurrency;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Support\CurrencyContext;
use App\Support\DataTable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/** Currencies the storefront sells in, their exchange rates, and the base (default) currency. */
class CurrencyController extends Controller
{
    /** Sample price (in base minor units) shown converted in the list, so a wrong rate is obvious. */
    private const SAMPLE = 10000;

    public function index(Request $request, CurrencyContext $currencies): Response
    {
        $this->authorize('currencies.view');

        $rows = QueryBuilder::for(Currency::query())
            ->allowedFilters(...[
                AllowedFilter::callback('search', fn (Builder $q, $term) => $q->where(fn (Builder $w) => $w
                    ->where('code', 'like', DataTable::like($term))->orWhere('name', 'like', DataTable::like($term)))),
                AllowedFilter::callback('status', fn (Builder $q, $v) => $q->where('is_active', $v === 'active')),
            ])
            ->allowedSorts(...['code', 'rate', 'sort_order'])
            ->defaultSort('-is_default', 'sort_order', 'code')
            ->paginate(DataTable::perPage($request))
            ->withQueryString();

        $user = $request->user();
        $base = $currencies->base();

        return Inertia::render('Admin/Currencies/Index', [
            'currencies' => $rows->through(fn (Currency $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->label(),
                'symbol' => $c->symbol(),
                'rate' => (float) $c->rate,
                'value' => $this->unitValue($c, $base->code),
                'sample' => Money::format($c->fromBase(self::SAMPLE), $c->code),
                'example' => __('currencies.make_default_example', [
                    'from' => Money::format(self::SAMPLE, $base->code),
                    'to' => Money::format($c->fromBase(self::SAMPLE), $c->code),
                ]),
                'is_default' => $c->is_default,
                'is_active' => $c->is_active,
                'edit_url' => $user->can('currencies.update') ? route('admin.currencies.edit', $c) : null,
                'delete_url' => ! $c->is_default && $user->can('currencies.delete') ? route('admin.currencies.destroy', $c) : null,
                'default_url' => ! $c->is_default && $c->is_active && $user->can('currencies.set_default') ? route('admin.currencies.default', $c) : null,
            ]),
            'base' => ['code' => $base->code, 'sample' => Money::format(self::SAMPLE, $base->code)],
            'state' => DataTable::state($request),
        ]);
    }

    public function create(CurrencyContext $currencies): Response
    {
        $this->authorize('currencies.create');

        return Inertia::render('Admin/Currencies/Form', ['currency' => null, 'base' => $currencies->base()->code, 'locales' => config('translatable.locales')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('currencies.create');
        $currency = Currency::query()->create($this->validated($request));

        return to_route('admin.currencies.edit', $currency)->with('success', __('admin.created'));
    }

    public function edit(Currency $currency, CurrencyContext $currencies): Response
    {
        $this->authorize('currencies.view');

        return Inertia::render('Admin/Currencies/Form', [
            'currency' => [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'value' => $currency->is_default ? 1 : round(1 / max((float) $currency->rate, 1e-8), 6),
                'is_default' => $currency->is_default,
                'is_active' => $currency->is_active,
                'sort_order' => $currency->sort_order,
            ],
            'base' => $currencies->base()->code,
            'locales' => config('translatable.locales'),
        ]);
    }

    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $this->authorize('currencies.update');
        $data = $this->validated($request, $currency);

        if ($currency->is_default) {
            // The base currency is always active at rate 1; its code changes only by picking another default.
            $data = ['name' => $data['name'], 'sort_order' => $data['sort_order']];
        }
        $currency->update($data);

        return back()->with('success', __('admin.updated'));
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        $this->authorize('currencies.delete');
        if ($currency->is_default) {
            return back()->with('error', __('currencies.cannot_delete_default'));
        }
        $currency->delete();

        return to_route('admin.currencies.index')->with('success', __('admin.deleted'));
    }

    /** Converts every stored price to this currency and makes it the base (see SetBaseCurrency). */
    public function makeDefault(Currency $currency, SetBaseCurrency $action): RedirectResponse
    {
        $this->authorize('currencies.set_default');
        $action->handle($currency);

        return back()->with('success', __('currencies.default_changed', ['code' => $currency->code]));
    }

    /** "1 USD = ₪3.70": what one unit of this currency is worth in the base currency. */
    private function unitValue(Currency $currency, string $baseCode): string
    {
        return '1 '.$currency->code.' = '.Money::format((int) round(100 / max((float) $currency->rate, 1e-8)), $baseCode);
    }

    private function validated(Request $request, ?Currency $currency = null): array
    {
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[A-Z]{3}$/', Rule::unique('currencies', 'code')->ignore($currency?->id)],
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:60'],
            'name.en' => ['nullable', 'string', 'max:60'],
            'name.he' => ['nullable', 'string', 'max:60'],
            // Owners think "1 USD = 3.70 ₪": we ask that, and store the inverse (units per 1 base unit).
            'value' => ['required', 'numeric', 'min:0.0001', 'max:100000'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $data['name'] = array_filter($data['name'], fn ($v) => filled($v));
        $data['sort_order'] ??= 0;

        // Keep the stored rate when the typed value is just its rounded display (no drift on unrelated edits).
        $rate = 1 / (float) $data['value'];
        $unchanged = $currency !== null && abs(round(1 / max((float) $currency->rate, 1e-8), 6) - (float) $data['value']) < 1e-9;
        $data['rate'] = $unchanged ? $currency->rate : sprintf('%.8F', $rate);
        unset($data['value']);

        return $data;
    }
}
