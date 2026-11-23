<?php

namespace App\Actions\Fortify;

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
        ], attributes: [
            'name' => __('cart.name'),
            'email' => __('common.email'),
            'phone' => __('cart.phone'),
            'password' => __('common.password'),
        ])->validate();

        $user = User::query()->create([
            'name' => trim($data['name']),
            'email' => strtolower($data['email']),
            'phone' => preg_replace('/[\s\-]/', '', $data['phone']),
            'password' => $data['password'],
            'locale' => app()->getLocale(),
        ]);

        return $user->assignRole('customer');
    }
}
