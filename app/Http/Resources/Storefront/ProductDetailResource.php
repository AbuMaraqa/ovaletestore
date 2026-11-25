<?php

namespace App\Http\Resources\Storefront;

use App\Http\Resources\ImageResource;
use App\Models\AttributeTerm;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Support\CurrencyContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Product page payload. Variation availability is computed server-side; the client only
 * matches the selected term ids against `variants[].term_ids`.
 *
 * Requires: translations, brand.translations, categories.translations, media,
 * productAttributes.attribute.translations, productAttributes.terms.translations, activeVariants.terms, activeVariants.media
 *
 * @mixin Product
 */
class ProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $category = $this->categories->sortByDesc('pivot.is_primary')->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'description' => $this->description, // sanitized on write (App\Support\RichText)
            'is_variable' => $this->isVariable(),
            'brand' => $this->brand ? ['name' => $this->brand->name] : null,
            'category' => $category ? [
                'name' => $category->name,
                'url' => route('storefront.category', ['slug' => $category->slug]),
            ] : null,
            'images' => ImageResource::collection($this->getMedia('gallery')),
            'attributes' => $this->productAttributes
                ->filter(fn (ProductAttribute $pa) => $pa->is_visible || $pa->used_for_variations)
                ->map(fn (ProductAttribute $pa) => [
                    'id' => $pa->attribute_id,
                    'name' => $pa->attribute->name,
                    'display_type' => $pa->attribute->display_type->value,
                    'used_for_variations' => $pa->used_for_variations,
                    'terms' => $pa->terms->map(fn (AttributeTerm $t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'color' => $t->color_hex,
                    ])->values(),
                ])->values(),
            'variants' => $this->activeVariants->map(fn (ProductVariant $v) => [
                // Shown in the visitor's currency (CurrencyContext); stored prices are in the base currency.
                'id' => $v->id,
                'term_ids' => $v->terms->pluck('id')->sort()->values(),
                'sku' => $v->sku,
                'price' => app(CurrencyContext::class)->format($v->effectivePrice()),
                'regular_price' => $v->effectivePrice() < $v->regular_price ? app(CurrencyContext::class)->format($v->regular_price) : null,
                'trade_price' => $v->hasTradePrice(),
                'on_sale' => $v->isOnSale(),
                'discount_percent' => $v->discountPercent(),
                'saving' => $v->effectivePrice() < $v->regular_price ? app(CurrencyContext::class)->formatConverted(app(CurrencyContext::class)->convert($v->regular_price) - app(CurrencyContext::class)->convert($v->effectivePrice())) : null,
                // Only short, real deadlines (≤ 14 days) drive the countdown — no fake urgency.
                'sale_ends_at' => $v->isOnSale() && $v->sale_ends_at && $v->sale_ends_at->lte(now()->addDays(14))
                    ? $v->sale_ends_at->toIso8601String() : null,
                'purchasable' => $v->isPurchasable(),
                'max_quantity' => $v->availableQuantity(),
                'stock_status' => $v->stock_status->value,
                'is_default' => $v->is_default,
                'image' => ($media = $v->getFirstMedia('image')) ? new ImageResource($media) : null,
            ])->values(),
        ];
    }
}
