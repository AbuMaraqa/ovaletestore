<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\CurrencyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The visitor picks the currency the storefront shows and charges in (kept in the session). */
class CurrencyController extends Controller
{
    public function __invoke(Request $request, CurrencyContext $currencies): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', Rule::in($currencies->active()->pluck('code')->all())],
        ]);
        $request->session()->put(CurrencyContext::SESSION_KEY, $data['code']);

        return back();
    }
}
