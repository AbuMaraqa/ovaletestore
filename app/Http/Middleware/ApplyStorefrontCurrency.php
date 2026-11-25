<?php

namespace App\Http\Middleware;

use App\Support\CurrencyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Storefront pages show and charge in the visitor's chosen currency (session); everything else stays in the base. */
class ApplyStorefrontCurrency
{
    public function __construct(private readonly CurrencyContext $currencies) {}

    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->hasSession() ? $request->session()->get(CurrencyContext::SESSION_KEY) : null;
        $this->currencies->use(is_string($code) ? $code : null);

        return $next($request);
    }
}
