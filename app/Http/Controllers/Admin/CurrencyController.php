<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Currencies\SetBaseCurrency;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Support\CurrencyContext;
use App\Tables\Admin\CurrenciesTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Currencies the storefront sells in, their exchange rates, and the base (default) currency. */
class CurrencyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('currencies.view');

        return Inertia::render('Admin/Currencies/Index', (new CurrenciesTable($request))->props('currencies'));
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
