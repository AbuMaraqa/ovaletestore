<?php

namespace App\Http\Resources\Admin;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Shape mirrors ProductRequest so the Vue form can submit it back unchanged. @mixin Product */
class ProductFormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primary = $this->categories->firstWhere('pivot.is_primary', true);

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'brand_id' => $this->brand_id,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at?->toDateTimeLocalString('minute'),
            'translations' => collect(config('translatable.locales'))->mapWithKeys(function (string $locale) {
                $t = $this->translate($locale, false);

                return [$locale => [
                    'name' => $t?->name ?? '',
                    'slug' => $t?->slug ?? '',
                    'short_description' => $t?->short_description ?? '',
                    'description' => $t?->description ?? '',
                    'meta_title' => $t?->meta_title ?? '',
                    'meta_description' => $t?->meta_description ?? '',
                ]];
            }),
            'category_ids' => $this->categories->pluck('id'),
            'primary_category_id' => $primary?->id,
            'attributes' => $this->productAttributes->map(fn (ProductAttribute $pa) => [
                'attribute_id' => $pa->attribute_id,
                'term_ids' => $pa->terms->pluck('id'),
                'used_for_variations' => $pa->used_for_variations,
                'is_visible' => $pa->is_visible,
            ]),
            'variants' => $this->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'term_ids' => $v->terms->pluck('id')->sort()->values(),
                'sku' => $v->sku,
                'regular_price' => Money::toMajor($v->regular_price),
                'sale_price' => Money::toMajor($v->sale_price),
                'sale_starts_at' => $v->sale_starts_at?->toDateTimeLocalString('minute'),
                'sale_ends_at' => $v->sale_ends_at?->toDateTimeLocalString('minute'),
                'manage_stock' => $v->manage_stock,
                'stock_quantity' => $v->stock_quantity,
                'stock_status' => $v->stock_status->value,
                'weight_grams' => $v->weight_grams,
                'length_mm' => $v->length_mm,
                'width_mm' => $v->width_mm,
                'height_mm' => $v->height_mm,
                'is_active' => $v->is_active,
                'is_default' => $v->is_default,
                'tier_prices' => (object) $v->tierPrices->mapWithKeys(fn ($p) => [$p->price_tier_id => Money::toMajor($p->price)])->all(),
            ]),
            'media' => $this->getMedia('gallery')->map(fn (Media $m) => [
                'id' => $m->id,
                'url' => $m->hasGeneratedConversion('thumb') ? $m->getUrl('thumb') : $m->getUrl(),
                'name' => $m->name,
                'size' => $m->human_readable_size,
            ]),
            'view_url' => $this->isPublished() ? route('storefront.product', ['slug' => $this->slug]) : null,
        ];
    }
}
