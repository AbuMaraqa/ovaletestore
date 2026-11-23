<?php

namespace App\Http\Requests\Storefront;

use App\Models\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    private ShippingMethod|false|null $resolvedShippingMethod = false;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $needsAddress = fn (): bool => $this->shippingMethod()?->type->requiresAddress() ?? true;

        return [
            'customer_name' => ['required', 'string', 'min:2', 'max:120'],
            'customer_phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            'customer_email' => ['nullable', 'email:rfc', 'max:190'],
            'shipping_method_id' => ['required', 'integer', Rule::exists('shipping_methods', 'id')->where('is_active', true)],
            'shipping_city' => [Rule::requiredIf($needsAddress), 'nullable', 'string', 'max:120'],
            'shipping_address' => [Rule::requiredIf($needsAddress), 'nullable', 'string', 'max:500'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_name' => __('cart.name'),
            'customer_phone' => __('cart.phone'),
            'customer_email' => __('cart.email_optional'),
            'shipping_method_id' => __('cart.shipping_method'),
            'shipping_city' => __('cart.city'),
            'shipping_address' => __('cart.address'),
            'customer_note' => __('cart.note'),
        ];
    }

    public function shippingMethod(): ?ShippingMethod
    {
        if ($this->resolvedShippingMethod === false) {
            $id = filter_var($this->input('shipping_method_id'), FILTER_VALIDATE_INT);
            $this->resolvedShippingMethod = $id ? ShippingMethod::query()->find($id) : null;
        }

        return $this->resolvedShippingMethod;
    }

    /** @return array{customer_name:string, customer_email:?string, customer_phone:string, shipping_city:?string, shipping_address:?string, customer_note:?string} */
    public function customer(): array
    {
        $data = $this->safe()->except('shipping_method_id');
        $data['customer_phone'] = preg_replace('/[\s\-]/', '', $data['customer_phone']);
        $data['customer_email'] ??= null;
        $data['customer_note'] ??= null;
        $data['shipping_city'] ??= null;
        $data['shipping_address'] ??= null;

        return $data;
    }
}
