<?php

namespace App\Migration\Woo;

use Generator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Read-only access to the authenticated WooCommerce REST API (wc/v3) through a normal wp-admin login:
 * signs in with WOO_ADMIN_USER / WOO_ADMIN_PASSWORD (env only), then uses WordPress cookie auth + a REST nonce.
 * Only GET requests are made after sign-in; nothing on the source site is changed.
 */
class AdminApiClient
{
    private CookieJar $cookies;

    private ?string $nonce = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $user,
        private readonly string $password,
        private readonly int $perPage = 100,
        private readonly int $timeout = 60,
    ) {
        $this->cookies = new CookieJar;
    }

    public static function fromConfig(): self
    {
        $user = (string) config('services.woocommerce.admin_user');
        $password = (string) config('services.woocommerce.admin_password');

        if ($user === '' || $password === '') {
            throw new RuntimeException('Set WOO_ADMIN_USER and WOO_ADMIN_PASSWORD in .env to read orders.');
        }

        return new self(
            rtrim((string) config('services.woocommerce.base_url'), '/'),
            $user,
            $password,
            (int) config('services.woocommerce.per_page', 100),
            (int) config('services.woocommerce.timeout', 60),
        );
    }

    public function login(): void
    {
        // WordPress refuses a login whose browser did not accept its test cookie.
        $this->http()->get('/wp-login.php');

        $this->http()->asForm()->withoutRedirecting()->post('/wp-login.php', [
            'log' => $this->user,
            'pwd' => $this->password,
            'rememberme' => 'forever',
            'redirect_to' => $this->baseUrl.'/wp-admin/',
            'testcookie' => '1',
        ]);

        // A REST nonce is only issued to a signed-in user ("0" otherwise): this is the real login check.
        $nonce = trim($this->http()->get('/wp-admin/admin-ajax.php', ['action' => 'rest-nonce'])->body());
        if (preg_match('/^[a-f0-9]{8,}$/', $nonce) !== 1) {
            throw new RuntimeException('WordPress login failed (check WOO_ADMIN_USER / WOO_ADMIN_PASSWORD, or a security plugin blocking automated logins).');
        }

        $this->nonce = $nonce;
    }

    /** @return array{total:int, pages:int} */
    public function orderTotals(): array
    {
        $response = $this->api()->get('/orders', ['per_page' => 1, 'status' => 'any'])->throw();
        $total = (int) $response->header('X-WP-Total');

        return ['total' => $total, 'pages' => (int) ceil($total / $this->perPage)];
    }

    /** @return Generator<int, list<array<string, mixed>>> page number => orders, oldest first */
    public function orderPages(): Generator
    {
        $page = 1;

        do {
            $response = $this->api()->get('/orders', [
                'per_page' => $this->perPage,
                'page' => $page,
                'status' => 'any',
                'orderby' => 'id',
                'order' => 'asc',
            ])->throw();

            $items = $response->json() ?? [];
            if ($items !== []) {
                yield $page => $items;
            }

            $totalPages = (int) $response->header('X-WP-TotalPages');
            $page++;
        } while ($items !== [] && $page <= $totalPages);
    }

    /** @return list<array<string, mixed>> */
    public function orderNotes(int $orderId): array
    {
        return $this->api()->get("/orders/{$orderId}/notes", ['type' => 'any'])->throw()->json() ?? [];
    }

    private function api(): PendingRequest
    {
        if ($this->nonce === null) {
            $this->login();
        }

        return $this->http()
            ->baseUrl($this->baseUrl.'/wp-json/wc/v3')
            ->acceptJson()
            ->withHeaders(['X-WP-Nonce' => (string) $this->nonce]);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withOptions(['cookies' => $this->cookies]) // the GET of wp-login.php sets wordpress_test_cookie here
            ->withUserAgent('Mozilla/5.0 (compatible; OvaleteMigrator/1.0)')
            ->timeout($this->timeout)
            ->retry(3, 1500, throw: false);
    }
}
