<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A company (B2B customer on account), from the admin or from the POS. Authorization is done by the
 * controllers (companies.* in the admin, pos.companies.* in the POS). Internal notes and the active
 * flag are admin-only: the POS never sends or changes them.
 */
class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $company = $this->route('company');
        $ignore = $company instanceof Company ? $company->id : null;

        return [
            'name' => ['required', 'string', 'max:160', Rule::unique('companies', 'name')->ignore($ignore)],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            ...($this->fromPos() ? [] : [
                'notes' => ['nullable', 'string', 'max:2000'],
                'is_active' => ['boolean'],
            ]),
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('pos.company_name'),
            'contact_name' => __('pos.contact_name'),
            'phone' => __('pos.phone'),
            'city' => __('pos.city'),
            'address' => __('pos.address'),
        ];
    }

    /** @return array<string, mixed> normalized values (phone without spaces/dashes) */
    public function company(): array
    {
        $data = $this->validated();
        $data['phone'] = isset($data['phone']) ? preg_replace('/[\s\-]/', '', (string) $data['phone']) : null;
        if (! $this->fromPos()) {
            $data['is_active'] ??= true;
        }

        return $data;
    }

    private function fromPos(): bool
    {
        return $this->routeIs('pos.*');
    }
}
