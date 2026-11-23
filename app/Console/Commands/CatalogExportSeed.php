<?php

namespace App\Console\Commands;

use App\Models\BrandTranslation;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Snapshots the migrated catalog (categories, products, translations, prices, stock, image source URLs)
 * into database/data/catalog/*.json, which Database\Seeders\CatalogSeeder loads on any fresh install —
 * no WooCommerce access needed. Keyed by the WooCommerce source id, so re-seeding updates in place.
 */
#[Signature('catalog:export-seed {--path= : Output folder (default: database/data/catalog)}')]
#[Description('Export the current catalog (with prices and translations) as seed data for CatalogSeeder')]
class CatalogExportSeed extends Command
{
    public function handle(): int
    {
        $dir = $this->option('path') ?: database_path('data/catalog');
        File::ensureDirectoryExists($dir);
        $locales = (array) config('translatable.locales');

        $categorySourceIds = Category::query()->pluck('source_id', 'id')->all();
        $brandSlugs = BrandTranslation::query()->where('locale', 'en')->pluck('slug', 'brand_id')->all();

        $categories = [];
        foreach (Category::query()->whereNotNull('source_id')->with('translations')->orderBy('sort_order')->get() as $c) {
            $categories[] = [
                'source_id' => $c->source_id,
                'parent_source_id' => $c->parent_id ? ($categorySourceIds[$c->parent_id] ?? null) : null,
                'sort_order' => $c->sort_order,
                'is_active' => (bool) $c->is_active,
                'translations' => $this->translations($c, $locales, ['name', 'slug', 'description']),
            ];
        }

        $products = [];
        $query = Product::query()->whereNotNull('source_id')->with(['translations', 'categories', 'variants', 'media'])->orderBy('source_id');
        foreach ($query->get() as $p) {
            /** @var ProductVariant|null $variant */
            $variant = $p->variants->firstWhere('signature', '');
            $images = [];
            foreach ($p->media->where('collection_name', 'gallery')->sortBy('order_column') as $media) {
                /** @var Media $media */
                if ($url = $media->getCustomProperty('source_url')) {
                    $images[] = $url;
                }
            }

            $products[] = [
                'source_id' => $p->source_id,
                'status' => $p->getRawOriginal('status'),
                'is_featured' => (bool) $p->is_featured,
                'sort_order' => $p->sort_order,
                'brand' => $p->brand_id ? ($brandSlugs[$p->brand_id] ?? null) : null,
                'categories' => $p->categories->sortByDesc('pivot.is_primary')->pluck('source_id')->filter()->values()->all(),
                'sku' => $variant?->sku,
                'regular_price' => $variant?->regular_price, // minor units (agorot)
                'sale_price' => $variant?->sale_price,
                'stock_status' => $variant?->getRawOriginal('stock_status'),
                'manage_stock' => (bool) $variant?->manage_stock,
                'stock_quantity' => $variant?->stock_quantity,
                'images' => $images,
                'translations' => $this->translations($p, $locales, ['name', 'slug', 'short_description', 'description']),
            ];
        }

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        File::put("{$dir}/categories.json", json_encode($categories, $flags)."\n");
        File::put("{$dir}/products.json", json_encode($products, $flags)."\n");

        $this->components->info(sprintf('Exported %d categories and %d products to %s', count($categories), count($products), $dir));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $locales
     * @param  list<string>  $fields
     * @return array<string, array<string, mixed>>
     */
    private function translations(Product|Category $model, array $locales, array $fields): array
    {
        $out = [];
        foreach ($locales as $locale) {
            if ($model->hasTranslation($locale)) {
                $row = $model->translate($locale);
                $out[$locale] = collect($fields)->mapWithKeys(fn ($f) => [$f => $row->getAttribute($f)])->all();
            }
        }

        return $out;
    }
}
