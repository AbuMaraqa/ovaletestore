<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sales reps add a new company in the field, or correct its details (contact, phone, city, address).
 * Internal notes, the active flag, balances and payments stay in the admin. Every change is in the
 * activity log with the rep as the author.
 */
class PosCompanyController extends Controller
{
    public function create(): Response
    {
        $this->authorize('pos.companies.create');

        return Inertia::render('Pos/CompanyForm', [
            'company' => null,
            'seo' => SeoData::make(__('pos.new_company'))->noindex(),
        ]);
    }

    /** A new company goes straight to its order screen. */
    public function store(CompanyRequest $request): RedirectResponse
    {
        $this->authorize('pos.companies.create');
        $company = Company::query()->create([...$request->company(), 'is_active' => true]);

        return to_route('pos.create', $company)->with('success', __('pos.company_created', ['name' => $company->name]));
    }

    public function edit(Company $company): Response
    {
        $this->authorize('pos.companies.update');
        abort_unless($company->is_active, 404);

        return Inertia::render('Pos/CompanyForm', [
            'company' => $company->only(['id', 'name', 'contact_name', 'phone', 'city', 'address']),
            'seo' => SeoData::make(__('pos.edit_company').' — '.$company->name)->noindex(),
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $this->authorize('pos.companies.update');
        abort_unless($company->is_active, 404);
        $company->update($request->company());

        return to_route('pos.create', $company)->with('success', __('pos.company_updated'));
    }
}
