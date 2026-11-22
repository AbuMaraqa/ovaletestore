<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'min:2', 'max:120'],
            'customer_phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            'customer_email' => ['nullable', 'email:rfc', 'max:190'],
            'shipping_city' => ['required', 'string', 'max:120'],
            'shipping_address' => ['required', 'string', 'max:500'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_name' => __('cart.name'),
            'customer_phone' => __('cart.phone'),
            'customer_email' => __('cart.email_optional'),
            'shipping_city' => __('cart.city'),
            'shipping_address' => __('cart.address'),
            'customer_note' => __('cart.note'),
        ];
    }

    /** @return array{customer_name:string, customer_email:?string, customer_phone:string, shipping_city:string, shipping_address:string, customer_note:?string} */
    public function customer(): array
    {
        $data = $this->validated();
        $data['customer_phone'] = preg_replace('/[\s\-]/', '', $data['customer_phone']);
        $data['customer_email'] ??= null;
        $data['customer_note'] ??= null;

        return $data;
    }
}
