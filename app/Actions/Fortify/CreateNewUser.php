<?php

namespace App\Actions\Fortify;

use App\Actions\Traders\RequestTraderAccount;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Public customer sign-up. Customers get the `customer` role only — no admin permissions,
 * so `access-admin` stays false for them.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /** @param array<string, mixed> $input */
    public function create(array $input): User
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', Rule::unique(User::class, 'email')],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            'password' => $this->passwordRules(),
            // Optional trader (wholesale) request; reviewed by staff before any trader price applies.
            'trader_request' => ['nullable', 'boolean'],
            'business_name' => ['nullable', 'required_if_accepted:trader_request', 'string', 'min:2', 'max:150'],
        ], attributes: [
            'name' => __('cart.name'),
            'email' => __('common.email'),
            'phone' => __('cart.phone'),
            'password' => __('common.password'),
            'business_name' => __('traders.business_name'),
        ])->validate();

        $user = User::query()->create([
            'name' => trim($data['name']),
            'email' => strtolower($data['email']),
            'phone' => preg_replace('/[\s\-]/', '', $data['phone']),
            'password' => $data['password'],
            'locale' => app()->getLocale(),
        ]);

        $user->assignRole('customer');

        if (! empty($data['trader_request'])) {
            app(RequestTraderAccount::class)->handle($user, trim((string) $data['business_name']));
        }

        return $user;
    }
}
