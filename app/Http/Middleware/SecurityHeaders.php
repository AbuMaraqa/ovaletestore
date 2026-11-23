<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Baseline security headers (CSP is handled by spatie/laravel-csp). */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->remove('X-Powered-By');

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Admin pages and personalised pages must never be stored by shared caches.
        if ($request->routeIs('admin.*', 'storefront.cart', 'storefront.checkout', 'storefront.order.thanks', 'account.*', 'login', 'register', 'password.*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
