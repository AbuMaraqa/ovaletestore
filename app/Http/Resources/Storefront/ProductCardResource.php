<?php

namespace App\Http\Resources\Storefront;

use App\Http\Resources\ImageResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Listing card. Requires: translations, activeVariants, media (+ categories.translations for the category label).
 *
 * @mixin Product
 */
class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variants = $this->activeVariants;
        $prices = $variants->map(fn (ProductVariant $v) => $v->effectivePrice());
        $min = (int) $prices->min();
        $max = (int) $prices->max();
        $default = $variants->firstWhere('is_default', true) ?? $variants->first();
        // The struck-through price must belong to the variant whose price is shown ("from" = cheapest).
        $cheapest = $variants->sortBy(fn (ProductVariant $v) => $v->effectivePrice())->first();
        $image = $this->getFirstMedia('gallery');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => route('storefront.product', ['slug' => $this->slug]),
            'image' => $image ? new ImageResource($image) : null,
            'is_variable' => $this->isVariable(),
            'price' => Money::format($min),
            'price_max' => $max > $min ? Money::format($max) : null,
            'regular_price' => $cheapest && $cheapest->isOnSale() ? Money::format($cheapest->regular_price) : null,
            'on_sale' => $variants->contains(fn (ProductVariant $v) => $v->isOnSale()),
            // Best saving across variants ("up to -X%" for variable products).
            'discount_percent' => $variants->map(fn (ProductVariant $v) => $v->discountPercent())->filter()->max(),
            'in_stock' => $variants->contains(fn (ProductVariant $v) => $v->isPurchasable()),
            'category' => $this->relationLoaded('categories') ? $this->categories->sortByDesc('pivot.is_primary')->first()?->name : null,
            'default_variant_id' => ! $this->isVariable() ? $default?->id : null,
        ];
    }
}
