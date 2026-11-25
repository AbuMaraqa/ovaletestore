<?php

namespace Database\Seeders;

use App\Models\PriceTier;
use App\Models\PriceTierPrice;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Loads the old store's wholesale prices (php artisan woo:export-wholesale-prices) into the "wholesale"
 * price tier as exact per-variant prices. Idempotent (upsert). Matches simple products by WooCommerce id;
 * variations by SKU within their product, else every variant of the product gets the price.
 */
class WholesalePriceSeeder extends Seeder
{
    public function run(?string $path = null): void
    {
        $path ??= database_path('data/catalog/wholesale-prices.json');
        $tier = PriceTier::query()->where('code', 'wholesale')->first();
        if (! File::exists($path) || ! $tier) {
            $this->command?->warn('WholesalePriceSeeder skipped: run php artisan woo:export-wholesale-prices first (and keep the "wholesale" tier).');

            return;
        }

        $rows = collect(json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR)['prices'] ?? []);
        $products = Product::withTrashed()->whereIn('source_id', $rows->pluck('product_source_id')->unique())->with('variants')->get()->keyBy('source_id');
        $upserts = [];

        foreach ($rows as $row) {
            $product = $products->get($row['product_source_id']);
            if (! $product) {
                continue;
            }
            $variants = $row['sku'] && $row['variation_source_id']
                ? $product->variants->where('sku', $row['sku'])
                : collect();
            if ($variants->isEmpty()) {
                $variants = $row['variation_source_id'] ? $product->variants : $product->variants->where('is_default', true);
            }
            foreach ($variants as $variant) {
                $upserts[$variant->id] = [
                    'price_tier_id' => $tier->id, 'product_variant_id' => $variant->id, 'price' => Money::toMinor($row['price']),
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk(array_values($upserts), 500) as $chunk) {
            PriceTierPrice::query()->upsert($chunk, ['price_tier_id', 'product_variant_id'], ['price', 'updated_at']);
        }

        $this->command?->info(count($upserts).' wholesale prices loaded into the "'.$tier->label('ar').'" tier.');
    }
}
