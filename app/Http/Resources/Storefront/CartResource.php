<?php

namespace App\Http\Resources\Storefront;

use App\Http\Resources\ImageResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ShippingMethod;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cart */
class CartResource extends JsonResource
{
    public const RELATIONS = [
        'items.variant.product.translations',
        'items.variant.product.media',
        'items.variant.terms.translations',
        'items.variant.media',
    ];

    /** @param array{subtotal:int, discount_total:int, shipping_total:int, total:int, item_count:int, meets_minimum:bool, shipping_method:?ShippingMethod} $totals */
    public function __construct(Cart $cart, private readonly array $totals)
    {
        parent::__construct($cart);
    }

    public function toArray(Request $request): array
    {
        return [
            'items' => $this->items->map(function (CartItem $item) {
                $variant = $item->variant;
                $product = $variant->product;
                $media = $variant->getFirstMedia('image') ?? $product->getFirstMedia('gallery');

                return [
                    'id' => $item->id,
                    'name' => $product->name,
                    'url' => route('storefront.product', ['slug' => $product->slug]),
                    'variant_label' => $variant->terms->isNotEmpty() ? $variant->label() : null,
                    'image' => $media ? new ImageResource($media) : null,
                    'quantity' => $item->quantity,
                    'max_quantity' => $variant->availableQuantity(),
                    'unit_price' => Money::format($variant->effectivePrice()),
                    'line_total' => Money::format($variant->effectivePrice() * $item->quantity),
                    'available' => $product->isPublished() && $variant->isPurchasable(),
                ];
            })->values(),
            'item_count' => $this->totals['item_count'],
            'subtotal' => Money::format($this->totals['subtotal']),
            'shipping_total' => $this->totals['shipping_total'] > 0 ? Money::format($this->totals['shipping_total']) : null,
            'total' => Money::format($this->totals['total']),
            'meets_minimum' => $this->totals['meets_minimum'],
        ];
    }
}
