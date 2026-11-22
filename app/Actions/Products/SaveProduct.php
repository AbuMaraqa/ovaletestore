<?php

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Support\RichText;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a product with translations, categories, attributes and variants in one transaction.
 *
 * @phpstan-type ProductInput array{
 *   type: string, status: string, brand_id?: int|null, is_featured?: bool, published_at?: string|null,
 *   translations: array<string, array<string, string|null>>,
 *   category_ids?: list<int>, primary_category_id?: int|null,
 *   attributes?: list<array{attribute_id:int, term_ids:list<int>, used_for_variations?:bool, is_visible?:bool}>,
 *   variants: list<array<string, mixed>>
 * }
 */
class SaveProduct
{
    public function __construct(private readonly SyncProductVariants $syncVariants) {}

    /** @param ProductInput $data */
    public function handle(array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($data, $product): Product {
            $product ??= new Product;

            $product->fill([
                'type' => $data['type'],
                'status' => $data['status'],
                'brand_id' => $data['brand_id'] ?? null,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'published_at' => $data['published_at']
                    ?? ($data['status'] === ProductStatus::Published->value ? ($product->published_at ?? now()) : null),
            ]);

            $this->fillTranslations($product, $data['translations']);
            $product->save();

            $this->syncCategories($product, $data['category_ids'] ?? [], $data['primary_category_id'] ?? null);
            $this->syncAttributes($product, $data['attributes'] ?? []);

            $variants = $data['variants'];
            if ($product->type === ProductType::Simple) {
                $variants = [array_merge($variants[0] ?? [], ['term_ids' => [], 'is_default' => true])];
            }
            $this->syncVariants->handle($product, $variants);

            return $product->refresh();
        });
    }

    /** @param array<string, array<string, string|null>> $translations */
    private function fillTranslations(Product $product, array $translations): void
    {
        foreach (config('translatable.locales') as $locale) {
            $fields = $translations[$locale] ?? [];

            if (blank($fields['name'] ?? null)) {
                // Missing locale → remove stale row so the fallback chain applies.
                if ($product->exists && $product->hasTranslation($locale)) {
                    $product->deleteTranslations($locale);
                }

                continue;
            }

            $translation = $product->translateOrNew($locale);
            $translation->name = trim($fields['name']);
            $translation->slug = $fields['slug'] ?? $translation->slug;
            $translation->short_description = RichText::clean($fields['short_description'] ?? null);
            $translation->description = RichText::clean($fields['description'] ?? null);
            $translation->meta_title = $fields['meta_title'] ?? null;
            $translation->meta_description = $fields['meta_description'] ?? null;
        }
    }

    /** @param list<int> $categoryIds */
    private function syncCategories(Product $product, array $categoryIds, ?int $primaryId): void
    {
        $primaryId ??= $categoryIds[0] ?? null;

        $product->categories()->sync(
            collect($categoryIds)->mapWithKeys(fn (int $id) => [$id => ['is_primary' => $id === $primaryId]])->all(),
        );
    }

    /** @param list<array{attribute_id:int, term_ids:list<int>, used_for_variations?:bool, is_visible?:bool}> $attributes */
    private function syncAttributes(Product $product, array $attributes): void
    {
        $keep = [];

        foreach (array_values($attributes) as $index => $row) {
            $productAttribute = $product->productAttributes()->updateOrCreate(
                ['attribute_id' => $row['attribute_id']],
                [
                    'used_for_variations' => $product->type === ProductType::Variable && ($row['used_for_variations'] ?? true),
                    'is_visible' => $row['is_visible'] ?? true,
                    'sort_order' => $index,
                ],
            );
            $productAttribute->terms()->sync($row['term_ids']);
            $keep[] = $productAttribute->id;
        }

        $product->productAttributes()->whereNotIn('id', $keep)->delete();
    }
}
