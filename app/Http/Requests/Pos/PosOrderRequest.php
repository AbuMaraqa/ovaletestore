<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('access-pos');
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('is_active', true)],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.variant_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['company_id' => __('pos.company'), 'items' => __('pos.items'), 'note' => __('pos.note')];
    }

    /** @return array<int, int> variant id => quantity */
    public function quantities(): array
    {
        $out = [];
        foreach ($this->validated('items') as $item) {
            $out[(int) $item['variant_id']] = (int) $item['quantity'];
        }

        return $out;
    }
}
