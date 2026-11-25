<?php

namespace App\Console\Commands;

use App\Migration\Woo\AdminApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Reads the "WooCommerce Wholesale Prices" plugin values (product meta `wholesale_customer_wholesale_price`)
 * for every product and writes them for Database\Seeders\WholesalePriceSeeder. Read-only, paced for the
 * source firewall. Variable products: each variation's own value, else the parent's.
 */
#[Signature('woo:export-wholesale-prices {--path= : Output file (default: database/data/catalog/wholesale-prices.json)} {--delay=3000 : Pause between requests in ms}')]
#[Description('Export WooCommerce wholesale prices (read-only) for WholesalePriceSeeder')]
class WooExportWholesalePrices extends Command
{
    private const META = 'wholesale_customer_wholesale_price';

    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('data/catalog/wholesale-prices.json');
        $prices = [];

        try {
            $client = AdminApiClient::fromConfig();
            for ($page = 1; ; $page++) {
                $result = $client->get('products', ['per_page' => 100, 'page' => $page, 'status' => 'any']);
                if ($result['items'] === []) {
                    break;
                }
                foreach ($result['items'] as $product) {
                    $parent = $this->price($product);
                    if ($product['type'] === 'variable') {
                        $this->pause();
                        foreach ($client->get("products/{$product['id']}/variations", ['per_page' => 100])['items'] as $variation) {
                            $price = $this->price($variation) ?? $parent;
                            if ($price !== null) {
                                $prices[] = ['product_source_id' => $product['id'], 'variation_source_id' => $variation['id'], 'sku' => $variation['sku'] ?: null, 'price' => $price];
                            }
                        }
                    } elseif ($parent !== null) {
                        $prices[] = ['product_source_id' => $product['id'], 'variation_source_id' => null, 'sku' => $product['sku'] ?: null, 'price' => $parent];
                    }
                }
                $this->line("Page {$page}: ".count($prices).' wholesale prices so far');
                $this->pause();
            }
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(['exported_at' => now()->toIso8601String(), 'prices' => $prices], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->components->info(count($prices)." wholesale prices written to {$path}. Next: php artisan db:seed --class=WholesalePriceSeeder");

        return self::SUCCESS;
    }

    /** Major-unit string from the meta, or null when not set / zero. */
    private function price(array $item): ?string
    {
        $value = collect($item['meta_data'] ?? [])->firstWhere('key', self::META)['value'] ?? null;

        return is_numeric($value) && (float) $value > 0 ? number_format((float) $value, 2, '.', '') : null;
    }

    private function pause(): void
    {
        usleep(max(0, (int) $this->option('delay')) * 1000);
    }
}
