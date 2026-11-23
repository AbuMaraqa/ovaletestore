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
        $image = $this->getFirstMedia('gallery');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => route('storefront.product', ['slug' => $this->slug]),
            'image' => $image ? new ImageResource($image) : null,
            'is_variable' => $this->isVariable(),
            'price' => Money::format($min),
            'price_max' => $max > $min ? Money::format($max) : null,
            'regular_price' => $default && $default->isOnSale() ? Money::format($default->regular_price) : null,
            'on_sale' => $variants->contains(fn (ProductVariant $v) => $v->isOnSale()),
            'in_stock' => $variants->contains(fn (ProductVariant $v) => $v->isPurchasable()),
            'category' => $this->relationLoaded('categories') ? $this->categories->sortByDesc('pivot.is_primary')->first()?->name : null,
            'default_variant_id' => ! $this->isVariable() ? $default?->id : null,
        ];
    }
}
