<?php

namespace App\Http\Resources\Admin;

use App\Enums\StockStatus;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $prices = $this->variants->map->effectivePrice();
        $media = $this->getFirstMedia('gallery');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'is_featured' => $this->is_featured,
            'sku' => $this->variants->firstWhere('is_default', true)?->sku,
            'thumb' => $media ? ($media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl()) : null,
            'price' => $prices->isEmpty() ? null : (
                $prices->min() === $prices->max()
                    ? Money::format((int) $prices->min())
                    : Money::format((int) $prices->min()).' – '.Money::format((int) $prices->max())
            ),
            'variants_count' => $this->variants->count(),
            'categories' => $this->categories->pluck('name'),
            'brand' => $this->brand?->name,
            'in_stock' => $this->variants->contains(fn ($v) => $v->stock_status !== StockStatus::OutOfStock),
            'stock' => $this->variants->contains(fn ($v) => $v->manage_stock) ? (int) $this->variants->sum('stock_quantity') : null,
            'updated_at' => $this->updated_at?->diffForHumans(),
            'created_at' => $this->created_at?->format('Y-m-d'),
            'edit_url' => $request->user()?->can('products.update') ? route('admin.products.edit', $this->id) : null,
            'delete_url' => $request->user()?->can('products.delete') ? route('admin.products.destroy', $this->id) : null,
            'view_url' => $this->isPublished() ? route('storefront.product', ['slug' => $this->slug]) : null,
        ];
    }
}
