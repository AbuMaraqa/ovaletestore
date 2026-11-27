<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompanyPaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Models\Order;
use App\Queries\CompanyStatement;
use App\Support\Money;
use App\Support\Pdf\OrderDocuments;
use App\Tables\Admin\CompaniesTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** B2B companies the sales reps order for, with their account statement (orders on account − payments). */
class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('companies.view');

        return Inertia::render('Admin/Companies/Index', (new CompaniesTable($request))->props('companies') + [
            'outstanding' => Money::format(Company::query()->withBalance()->get()->sum(fn (Company $c) => max(0, $c->balance()))),
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
