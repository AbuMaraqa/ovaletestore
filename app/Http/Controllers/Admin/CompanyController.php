<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompanyPaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Models\Order;
use App\Queries\CompanyStatement;
use App\Support\DataTable;
use App\Support\Money;
use App\Support\Pdf\OrderDocuments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** B2B companies the sales reps order for, with their account statement (orders on account − payments). */
class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('companies.view');

        $companies = QueryBuilder::for(Company::query()->withBalance()->withCount('orders'))
            ->allowedFilters(...[
                AllowedFilter::callback('search', function (Builder $q, $term) {
                    $like = DataTable::like($term);
                    $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like)
                        ->orWhere('city', 'like', $like)->orWhere('contact_name', 'like', $like));
                }),
                AllowedFilter::callback('status', fn (Builder $q, $v) => $q->where('is_active', $v === 'active')),
                AllowedFilter::exact('city'),
            ])
            ->allowedSorts(...['name', 'orders_count', 'created_at', 'id'])
            ->defaultSort('name')
            ->paginate(DataTable::perPage($request))
            ->withQueryString();

        $outstanding = Company::query()->withBalance()->get()->sum(fn (Company $c) => max(0, $c->balance()));
        $user = $request->user();

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies->through(fn (Company $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'contact_name' => $c->contact_name,
                'phone' => $c->phone,
                'city' => $c->city,
                'is_active' => $c->is_active,
                'orders_count' => $c->orders_count,
                'balance' => Money::format($c->balance()),
                'has_debt' => $c->balance() > 0,
                'show_url' => route('admin.companies.show', $c),
                'edit_url' => $user->can('companies.update') ? route('admin.companies.edit', $c) : null,
                'delete_url' => $user->can('companies.delete') ? route('admin.companies.destroy', $c) : null,
            ]),
            'state' => DataTable::state($request),
            'outstanding' => Money::format($outstanding),
            'cities' => Company::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city')
                ->map(fn (string $city) => ['value' => $city, 'label' => $city]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('companies.create');

        return Inertia::render('Admin/Companies/Form', ['company' => null]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $this->authorize('companies.create');
        $company = Company::query()->create($request->company());

        return to_route('admin.companies.show', $company)->with('success', __('admin.created'));
    }

    public function show(Company $company): Response
    {
        $this->authorize('companies.view');

        return Inertia::render('Admin/Companies/Show', [
            'company' => [
                ...$company->only(['id', 'name', 'contact_name', 'phone', 'city', 'address', 'notes', 'is_active']),
                'balance' => Money::format($company->balance()),
                'has_debt' => $company->balance() > 0,
            ],
            'statement' => $this->statement($company),
            'payment_methods' => array_column(CompanyPaymentMethod::cases(), 'value'),
            'today' => now()->toDateString(),
        ]);
    }

    public function edit(Company $company): Response
    {
        $this->authorize('companies.update');

        return Inertia::render('Admin/Companies/Form', [
            'company' => $company->only(['id', 'name', 'contact_name', 'phone', 'city', 'address', 'notes', 'is_active']),
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $this->authorize('companies.update');
        $company->update($request->company());

        return to_route('admin.companies.show', $company)->with('success', __('admin.updated'));
    }

    /** Only companies without orders or payments can be deleted; otherwise deactivate them. */
    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('companies.delete');

        if ($company->orders()->exists() || $company->payments()->exists()) {
            return back()->with('error', __('admin.company_has_history'));
        }

        $company->delete();

        return to_route('admin.companies.index')->with('success', __('admin.deleted'));
    }

    /** @return array<string, mixed> */

    /**
     * The account statement for the screen (newest first, amounts formatted). Same data as the printed one.
     *
     * @return list<array<string, mixed>>
     */
    private function statement(Company $company): array
    {
        $rows = array_map(fn (array $e) => [
            ...$e,
            'date' => $e['date']->format('Y-m-d'),
            'debit' => $e['debit'] ? Money::format($e['debit']) : null,
            'credit' => $e['credit'] ? Money::format($e['credit']) : null,
            'balance' => Money::format($e['balance']),
        ], app(CompanyStatement::class)->entries($company));

        return array_reverse($rows);
    }

    /** Printed account statement (mPDF). */
    public function statementPdf(Company $company): HttpResponse
    {
        $this->authorize('companies.view');
        $file = 'statement-'.$company->id.'-'.now()->format('Ymd').'.pdf';

        return response(OrderDocuments::statement($company)->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$file.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
