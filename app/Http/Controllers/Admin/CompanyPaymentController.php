<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompanyPaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyPayment;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Payments received from a company, reducing its on-account balance. */
class CompanyPaymentController extends Controller
{
    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('companies.payments.create');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'method' => ['required', Rule::enum(CompanyPaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $company->payments()->create([
            ...$data,
            'amount' => (int) Money::toMinor($data['amount']),
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('success', __('admin.payment_recorded'));
    }

    public function destroy(Company $company, CompanyPayment $payment): RedirectResponse
    {
        $this->authorize('companies.payments.delete');
        abort_unless($payment->company_id === $company->id, 404);

        $payment->delete();

        return back()->with('success', __('admin.deleted'));
    }
}
