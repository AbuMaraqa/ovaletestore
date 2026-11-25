<?php

namespace App\Actions\Products;

use App\Enums\ProductType;
use App\Enums\StockStatus;
use App\Models\AttributeTerm;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Upserts a product's variants keyed by their term signature, removes variants that are no
 * longer submitted, and guarantees exactly one default variant.
 *
 * Each variant payload: term_ids, sku, regular_price, sale_price, manage_stock, stock_quantity,
 * stock_status, weight_grams, length_mm, width_mm, height_mm, is_active, is_default, tier_prices (prices in minor units).
 */
class SyncProductVariants
{
    /** @param  list<array<string, mixed>>  $variants */
    public function handle(Product $product, array $variants): void
    {
        DB::transaction(function () use ($product, $variants): void {
            $allowedTermIds = $this->allowedVariationTermIds($product);
            $keptIds = [];

            foreach (array_values($variants) as $index => $payload) {
                $termIds = array_map('intval', $payload['term_ids'] ?? []);
                $this->assertValidCombination($product, $termIds, $allowedTermIds, $index);

                $signature = ProductVariant::signatureFor($termIds);

                if (isset($keptIds[$signature])) {
                    throw ValidationException::withMessages([
                        "variants.{$index}.term_ids" => __('catalog.variants.duplicate'),
                    ]);
                }

                $variant = $product->variants()->updateOrCreate(
                    ['signature' => $signature],
                    [
                        'sku' => $payload['sku'] ?? null,
                        'regular_price' => (int) $payload['regular_price'],
                        'sale_price' => $payload['sale_price'] ?? null,
                        'sale_starts_at' => $payload['sale_starts_at'] ?? null,
                        'sale_ends_at' => $payload['sale_ends_at'] ?? null,
                        'manage_stock' => (bool) ($payload['manage_stock'] ?? false),
                        'stock_quantity' => $payload['stock_quantity'] ?? null,
                        'stock_status' => $payload['stock_status'] ?? StockStatus::InStock->value,
                        'weight_grams' => $payload['weight_grams'] ?? null,
                        'length_mm' => $payload['length_mm'] ?? null,
                        'width_mm' => $payload['width_mm'] ?? null,
                        'height_mm' => $payload['height_mm'] ?? null,
                        'is_active' => (bool) ($payload['is_active'] ?? true),
                        'is_default' => (bool) ($payload['is_default'] ?? false),
                        'sort_order' => $index,
                    ],
                );

                $variant->terms()->sync($termIds);
                if (array_key_exists('tier_prices', $payload)) {
                    $this->syncTierPrices($variant, (array) $payload['tier_prices']);
                }
                $keptIds[$signature] = $variant->id;
            }

            $product->variants()->whereNotIn('id', array_values($keptIds))->get()->each->delete();

            $this->ensureSingleDefault($product);
        });
    }

    /**
     * Exact trader prices per tier: a number sets it, empty removes it (the tier's automatic % applies).
     * Unknown tier ids are ignored. Saved through Eloquent so every change lands in the product history.
     *
     * @param  array<int|string, int|null>  $prices
     */
    private function syncTierPrices(ProductVariant $variant, array $prices): void
    {
        $tierIds = PriceTier::query()->whereIn('id', array_keys($prices))->pluck('id');

        foreach ($tierIds as $tierId) {
            $price = $prices[$tierId] ?? null;
            $existing = $variant->tierPrices()->where('price_tier_id', $tierId)->first();

            if ($price === null) {
                $existing?->delete();
            } elseif ($existing) {
                $existing->update(['price' => $price]);
            } else {
                $variant->tierPrices()->create(['price_tier_id' => $tierId, 'price' => $price]);
            }
        }
    }

    /** @return list<int>|null null = simple product (no terms allowed) */
    private function allowedVariationTermIds(Product $product): ?array
    {
        if ($product->type === ProductType::Simple) {
            return null;
        }

        return $product->productAttributes()
            ->where('used_for_variations', true)
            ->with('terms:attribute_terms.id')
            ->get()
            ->flatMap(fn ($pa) => $pa->terms->pluck('id'))
            ->all();
    }

    /** @param  list<int>  $termIds */
    private function assertValidCombination(Product $product, array $termIds, ?array $allowed, int $index): void
    {
        $key = "variants.{$index}.term_ids";

        if ($allowed === null) {
            if ($termIds !== []) {
                throw ValidationException::withMessages([$key => __('catalog.variants.simple_has_terms')]);
            }

            return;
        }

        $variationAttributeCount = $product->productAttributes()->where('used_for_variations', true)->count();

        if (count($termIds) !== $variationAttributeCount || array_diff($termIds, $allowed) !== []) {
            throw ValidationException::withMessages([$key => __('catalog.variants.invalid_combination')]);
        }

        // Exactly one term per attribute.
        $attributeIds = AttributeTerm::query()->whereIn('id', $termIds)->pluck('attribute_id');
        if ($attributeIds->unique()->count() !== count($termIds)) {
            throw ValidationException::withMessages([$key => __('catalog.variants.invalid_combination')]);
        }
    }

    private function ensureSingleDefault(Product $product): void
    {
        $variants = $product->variants()->get();

        if ($variants->isEmpty()) {
            return;
        }

        $default = $variants->firstWhere('is_default', true) ?? $variants->firstWhere('is_active', true) ?? $variants->first();

        $product->variants()->whereKeyNot($default->id)->where('is_default', true)->update(['is_default' => false]);

        if (! $default->is_default) {
            $default->update(['is_default' => true]);
        }
    }
}
