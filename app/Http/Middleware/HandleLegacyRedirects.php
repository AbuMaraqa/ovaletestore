<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves DB-managed 301s (old WooCommerce/TranslatePress URLs) — only for GET requests that would otherwise 404.
 */
class HandleLegacyRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! $request->isMethod('GET')) {
            return $response;
        }

        $redirect = Redirect::query()->where('from_path', Redirect::normalizePath($request->getPathInfo()))->first();

        if (! $redirect) {
            return $response;
        }

        $redirect->newQuery()->whereKey($redirect->id)->update(['hits' => $redirect->hits + 1, 'last_hit_at' => now()]);

        $target = str_starts_with($redirect->to_path, 'http') ? $redirect->to_path : url($redirect->to_path);

        return redirect()->to($target, $redirect->status_code);
    }
}
