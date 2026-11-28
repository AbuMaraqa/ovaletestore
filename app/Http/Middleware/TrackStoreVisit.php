<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shetabit\Visitor\Agent;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Internal traffic counter for the storefront (shetabit/visitor), shown in Admin → Site traffic without any
 * Google setup. Logs one row per real page view — a full page load or an Inertia visit — and nothing else:
 *  - GET with a 200 answer only (no redirects, errors, form posts, JSON helpers such as search suggestions);
 *  - no Inertia prefetches or partial reloads (filters, "load more" props);
 *  - no bots (the package's crawler detection), no staff browsing the store.
 * Written after the response has been sent (defer), so shoppers never wait for it. The person is not stored:
 * visitors are a daily-rotating hash of IP + browser, sessions a hash of the session id (StoreVisit scrubs the rest).
 */
class TrackStoreVisit
{
    private const SEARCH = ['google.', 'bing.', 'yahoo.', 'duckduckgo.', 'yandex.', 'baidu.', 'ecosia.'];

    private const SOCIAL = ['facebook.', 'fb.', 'instagram.', 'tiktok.', 'snapchat.', 't.co', 'x.com', 'twitter.',
        'telegram.', 't.me', 'whatsapp.', 'wa.me', 'youtube.', 'linkedin.', 'pinterest.', 'threads.'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request, $response)) {
            try {
                $fields = $this->fields($request);
                defer(fn () => rescue(fn () => visitor()->visit(null, $fields), report: true));
            } catch (Throwable $e) {
                report($e); // counting visits must never break the page
            }
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }
        if ($request->header('Purpose') === 'prefetch' || $request->hasHeader('X-Inertia-Partial-Data') || $request->hasHeader('X-Inertia-Partial-Except')) {
            return false;
        }
        if ($request->route()?->getName() === 'storefront.search.suggest' || $request->expectsJson() && ! $request->header('X-Inertia')) {
            return false;
        }
        if ($request->user()?->can('access-admin')) {
            return false;
        }

        $agent = new Agent;
        $agent->setHttpHeaders($request->server->all());
        $agent->setUserAgent((string) $request->userAgent());

        return $request->userAgent() !== null && ! $agent->isRobot();
    }

    /**
     * Report fields (moved into StoreVisit's own columns).
     *
     * @return array<string, string|null>
     */
    private function fields(Request $request): array
    {
        $agent = new Agent;
        $agent->setHttpHeaders($request->server->all());
        $agent->setUserAgent((string) $request->userAgent());
        $secret = (string) config('app.key');
        [$channel, $host] = $this->source($request);
        $slug = $request->route('slug');

        return [
            'path' => mb_substr(rawurldecode('/'.ltrim($request->path(), '/')), 0, 255),
            'subject' => is_string($slug) ? mb_substr(rawurldecode($slug), 0, 190) : null,
            'visitor_hash' => substr(hash_hmac('sha256', now()->toDateString().'|'.$request->ip().'|'.$request->userAgent(), $secret), 0, 16),
            'session_hash' => $request->hasSession() ? substr(hash_hmac('sha256', $request->session()->getId(), $secret), 0, 16) : null,
            'device_type' => match (true) {
                $agent->isTablet() => 'tablet',
                $agent->isMobile() => 'mobile',
                default => 'desktop',
            },
            'channel' => $channel,
            'referrer_host' => $host,
            'locale' => app()->getLocale(),
        ];
    }

    /**
     * Where the visit came from. Navigation inside the store (Inertia visits carry the current page as referrer)
     * has no channel, so channel/referrer counts are entrances, not every click.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function source(Request $request): array
    {
        $referer = (string) $request->headers->get('referer');
        $host = $referer !== '' ? strtolower((string) parse_url($referer, PHP_URL_HOST)) : '';
        $host = preg_replace('/^(www\.|m\.|l\.|lm\.)/', '', $host) ?? $host;

        if ($host === preg_replace('/^www\./', '', strtolower($request->getHost()))) {
            return [null, null];
        }

        $utm = strtolower((string) $request->query('utm_medium'));
        $matches = fn (array $needles) => collect($needles)->contains(fn (string $n) => str_starts_with($host, $n) || str_contains($host, '.'.$n));

        return match (true) {
            in_array($utm, ['cpc', 'ppc', 'paid', 'paidsearch'], true) => ['Paid Search', $host ?: null],
            $host === '' => ['Direct', null],
            $matches(self::SEARCH) => ['Organic Search', $host],
            $matches(self::SOCIAL) => ['Organic Social', $host],
            default => ['Referral', $host],
        };
    }
}
