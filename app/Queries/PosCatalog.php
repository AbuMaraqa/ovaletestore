<?php

namespace App\Queries;

use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;

/**
 * The whole sellable catalog in one compact payload for the POS (client-side search is instant on the iPad).
 * One entry per purchasable variant. Cached briefly per locale; prices are re-read when the order is placed.
 */
class PosCatalog
{
    public const CACHE_SECONDS = 300;

    /** @return array{categories: list<array{id:int, name:string}>, items: list<array<string, mixed>>} */
    public function get(): array
    {
        $locale = app()->getLocale();

        return Cache::remember("pos.catalog.{$locale}", self::CACHE_SECONDS, fn () => [
            'categories' => $this->categories(),
            'items' => $this->items(),
        ]);
    }

    public static function forget(): void
    {
        foreach (array_keys((array) config('laravellocalization.supportedLocales')) as $locale) {
            Cache::forget("pos.catalog.{$locale}");
        }
    }

    /** @return list<array{id:int, name:string}> */
    private function categories(): array
    {
        $out = [];
        foreach (Category::query()->active()->ordered()->with('translations')->get() as $category) {
            $out[] = ['id' => $category->id, 'name' => (string) $category->name];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function items(): array
    {
        $products = Product::query()->published()
            ->with(['translations', 'activeVariants.terms.translations', 'media', 'categories:id'])
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $items = [];
        foreach ($products as $product) {
            $image = $product->mainImage();
            $thumb = $image ? ($image->hasGeneratedConversion('thumb') ? $image->getUrl('thumb') : $image->getUrl()) : null;
            $categoryIds = $product->categories->pluck('id')->all();

            foreach ($product->activeVariants as $variant) {
                $price = $variant->effectivePrice();
                $items[] = [
                    'id' => $variant->id,
                    'name' => (string) $product->name,
                    'variant' => $variant->terms->isNotEmpty() ? $variant->label() : null,
                    'sku' => $variant->sku,
                    'price' => $price,
                    'price_formatted' => Money::format($price),
                    'on_sale' => $variant->isOnSale(),
                    'available' => $variant->isPurchasable(),
                    'max' => $variant->availableQuantity(),
                    'image' => $thumb,
                    'categories' => $categoryIds,
                ];
            }
        }

        return $items;
    }
}
