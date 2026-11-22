<?php

namespace App\Http\Resources\Storefront;

use App\Http\Resources\ImageResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => route('storefront.category', ['slug' => $this->slug]),
            'image' => $this->relationLoaded('media') && ($media = $this->getFirstMedia('image')) ? new ImageResource($media) : null,
            'products_count' => $this->whenCounted('products'),
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
