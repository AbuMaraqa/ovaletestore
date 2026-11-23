<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

/** After login/registration: staff → admin panel, sales reps → POS, customers → their account (or the page they came from). */
class AuthRedirectResponse implements LoginResponse, RegisterResponse, TwoFactorLoginResponse
{
    /** @param Request $request */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $user = $request->user();
        $default = match (true) {
            (bool) $user?->can('access-admin') => route('admin.dashboard'),
            (bool) $user?->can('access-pos') => route('pos.index'),
            default => route('account.dashboard'),
        };

        return redirect()->intended($default);
    }
}
