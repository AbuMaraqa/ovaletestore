<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompanyPaymentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyPayment;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** B2B companies the sales reps order for, with their account statement (orders on account − payments). */
class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('companies.manage');
        $search = trim((string) $request->query('search', ''));

        $companies = Company::query()->withBalance()->withCount('orders')
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('city', 'like', $like));
            })
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(25)->withQueryString();

        $outstanding = Company::query()->withBalance()->get()->sum(fn (Company $c) => max(0, $c->balance()));

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
            ]),
            'outstanding' => Money::format($outstanding),
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('companies.manage');

        return Inertia::render('Admin/Companies/Form', ['company' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('companies.manage');
        $company = Company::query()->create($this->validated($request));

        return to_route('admin.companies.show', $company)->with('success', __('admin.created'));
    }

    public function show(Company $company): Response
    {
        $this->authorize('companies.manage');

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
        $this->authorize('companies.manage');

        return Inertia::render('Admin/Companies/Form', [
            'company' => $company->only(['id', 'name', 'contact_name', 'phone', 'city', 'address', 'notes', 'is_active']),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('companies.manage');
        $company->update($this->validated($request, $company));

        return to_route('admin.companies.show', $company)->with('success', __('admin.updated'));
    }

    /** Only companies without orders or payments can be deleted; otherwise deactivate them. */
    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('companies.manage');

        if ($company->orders()->exists() || $company->payments()->exists()) {
            return back()->with('error', __('admin.company_has_history'));
        }

        $company->delete();

        return to_route('admin.companies.index')->with('success', __('admin.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Company $company = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('companies', 'name')->ignore($company?->id)],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);
        $data['phone'] = isset($data['phone']) ? preg_replace('/[\s\-]/', '', $data['phone']) : null;
        $data['is_active'] ??= true;

        return $data;
    }

    /**
     * Orders on account (debit) and payments (credit), oldest first, with a running balance.
     *
     * @return list<array<string, mixed>>
     */
    private function statement(Company $company): array
    {
        $entries = [];

        foreach ($company->orders()->where('payment_method', PaymentMethod::OnAccount->value)->with('salesRep:id,name')->get() as $order) {
            /** @var Order $order */
            $cancelled = $order->status === OrderStatus::Cancelled;
            $entries[] = [
                'type' => 'order',
                'id' => $order->id,
                'date' => $order->placed_at,
                'reference' => $order->number,
                'detail' => $order->salesRep?->getAttribute('name'),
                'status' => $order->status->value,
                'debit' => $cancelled ? 0 : $order->total,
                'credit' => 0,
            ];
        }

        foreach ($company->payments()->with('recorder:id,name')->get() as $payment) {
            /** @var CompanyPayment $payment */
            $entries[] = [
                'type' => 'payment',
                'id' => $payment->id,
                'date' => $payment->paid_at,
                'reference' => $payment->reference,
                'detail' => $payment->method->value,
                'note' => $payment->note,
                'status' => null,
                'debit' => 0,
                'credit' => $payment->amount,
            ];
        }

        usort($entries, fn ($a, $b) => [$a['date'], $a['type']] <=> [$b['date'], $b['type']]);

        $running = 0;
        foreach ($entries as &$entry) {
            $running += $entry['debit'] - $entry['credit'];
            $entry['date'] = $entry['date']->format('Y-m-d');
            $entry['debit'] = $entry['debit'] ? Money::format($entry['debit']) : null;
            $entry['credit'] = $entry['credit'] ? Money::format($entry['credit']) : null;
            $entry['balance'] = Money::format($running);
        }

        return array_reverse($entries); // newest first for display
    }
}
