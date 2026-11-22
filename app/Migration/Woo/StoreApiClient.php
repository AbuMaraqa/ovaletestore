<?php

namespace App\Migration\Woo;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the public WooCommerce Store API (wc/store/v1).
 * Yields products page by page so the full catalog is never held in memory.
 */
class StoreApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $perPage = 100,
        private readonly int $timeout = 60,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            rtrim((string) config('services.woocommerce.base_url'), '/'),
            (int) config('services.woocommerce.per_page', 100),
            (int) config('services.woocommerce.timeout', 60),
        );
    }

    /** @return array{total:int, pages:int} */
    public function productTotals(): array
    {
        $response = $this->request()->get('/products', ['per_page' => 1])->throw();

        return [
            'total' => (int) $response->header('X-WP-Total'),
            'pages' => (int) ceil(((int) $response->header('X-WP-Total')) / $this->perPage),
        ];
    }

    /** @return Generator<int, list<array<string, mixed>>> page number => products */
    public function productPages(int $startPage = 1): Generator
    {
        $page = $startPage;

        do {
            $response = $this->request()->get('/products', [
                'per_page' => $this->perPage,
                'page' => $page,
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
    public function categories(): array
    {
        $all = [];
        $page = 1;

        do {
            $items = $this->request()->get('/products/categories', ['per_page' => 100, 'page' => $page])->throw()->json() ?? [];
            $all = [...$all, ...$items];
            $page++;
        } while (count($items) === 100);

        return $all;
    }

    /** @return list<array<string, mixed>> */
    public function attributes(): array
    {
        return $this->request()->get('/products/attributes')->throw()->json() ?? [];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl.'/wp-json/wc/store/v1')
            ->acceptJson()
            ->withUserAgent('OvaleteMigrator/1.0')
            ->timeout($this->timeout)
            ->retry(3, 1500, throw: false);
    }
}
