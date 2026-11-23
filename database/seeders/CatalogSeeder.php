<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\StockStatus;
use App\Jobs\ImportRemoteImage;
use App\Models\BrandTranslation;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Loads the catalog snapshot in database/data/catalog (written by `php artisan catalog:export-seed`):
 * categories, products, their en/he/ar texts, prices (minor units) and stock.
 * Idempotent — keyed by the WooCommerce source id, so re-running updates instead of duplicating.
 * Images are queued from their original URLs only for products that have none yet (run the queue worker).
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $dir = database_path('data/catalog');
        if (! File::exists("{$dir}/products.json")) {
            $this->command?->warn('No catalog snapshot found — run `php artisan catalog:export-seed` first.');

            return;
        }

        $categories = json_decode(File::get("{$dir}/categories.json"), true, flags: JSON_THROW_ON_ERROR);
        $products = json_decode(File::get("{$dir}/products.json"), true, flags: JSON_THROW_ON_ERROR);

        $categoryIds = $this->seedCategories($categories);
        $brandIds = BrandTranslation::query()->where('locale', 'en')->pluck('brand_id', 'slug')->all();

        $queuedImages = 0;
        foreach (array_chunk($products, 100) as $chunk) {
            DB::transaction(function () use ($chunk, $categoryIds, $brandIds, &$queuedImages): void {
                foreach ($chunk as $row) {
                    $queuedImages += $this->seedProduct($row, $categoryIds, $brandIds);
                }
            });
        }

        $this->command?->info(sprintf('Catalog: %d categories, %d products seeded; %d images queued.', count($categories), count($products), $queuedImages));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, int> source id => local id
     */
    private function seedCategories(array $rows): array
    {
        $ids = [];
        // Parents first.
        usort($rows, fn ($a, $b) => ($a['parent_source_id'] !== null) <=> ($b['parent_source_id'] !== null));

        foreach ($rows as $row) {
            $category = Category::query()->firstOrNew(['source_id' => $row['source_id']]);
            $category->fill([
                'parent_id' => $row['parent_source_id'] ? ($ids[$row['parent_source_id']] ?? null) : null,
                'sort_order' => $row['sort_order'],
                'is_active' => $row['is_active'],
            ]);
            $this->applyTranslations($category, $row['translations']);
            $category->save();
            $ids[$row['source_id']] = $category->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, int>  $categoryIds
     * @param  array<string, int>  $brandIds
     * @return int images queued
     */
    private function seedProduct(array $row, array $categoryIds, array $brandIds): int
    {
        $product = Product::withTrashed()->firstOrNew(['source_id' => $row['source_id']]);
        $product->fill([
            'type' => ProductType::Simple,
            'status' => ProductStatus::from($row['status']),
            'is_featured' => $row['is_featured'],
            'sort_order' => $row['sort_order'],
            'brand_id' => $row['brand'] ? ($brandIds[$row['brand']] ?? $product->brand_id) : $product->brand_id,
            'published_at' => $product->published_at ?? now(),
        ]);
        $this->applyTranslations($product, $row['translations']);
        $product->save();
        if ($product->trashed()) {
            $product->restore();
        }

        $product->categories()->sync(collect($row['categories'])
            ->map(fn ($sourceId) => $categoryIds[$sourceId] ?? null)->filter()->values()
            ->mapWithKeys(fn ($id, $i) => [$id => ['is_primary' => $i === 0]])->all());

        // A SKU already owned by another product (e.g. created by hand) is left out rather than failing the seed.
        $sku = $row['sku'] && ProductVariant::query()->where('sku', $row['sku'])->where('product_id', '!=', $product->id)->doesntExist()
            ? $row['sku'] : null;

        ProductVariant::query()->updateOrCreate(
            ['product_id' => $product->id, 'signature' => ''],
            [
                'sku' => $sku,
                'regular_price' => (int) $row['regular_price'],
                'sale_price' => $row['sale_price'] !== null ? (int) $row['sale_price'] : null,
                'stock_status' => StockStatus::from($row['stock_status'] ?? 'in_stock'),
                'manage_stock' => $row['manage_stock'],
                'stock_quantity' => $row['stock_quantity'],
                'is_active' => true,
                'is_default' => true,
            ],
        );

        if ($product->media()->where('collection_name', 'gallery')->exists()) {
            return 0;
        }

        foreach ($row['images'] as $position => $url) {
            ImportRemoteImage::dispatch(Product::class, $product->id, $url, 'gallery', $position)->afterCommit();
        }

        return count($row['images']);
    }

    /** @param array<string, array<string, mixed>> $translations */
    private function applyTranslations(Model $model, array $translations): void
    {
        foreach ($translations as $locale => $fields) {
            $translation = $model->translateOrNew($locale);
            foreach ($fields as $field => $value) {
                $translation->setAttribute($field, $value);
            }
        }
    }
}
