<?php

namespace App\Http\Resources\Storefront;

use App\Http\Resources\ImageResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ShippingMethod;
use App\Support\CurrencyContext;
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

    /** @param array{subtotal:int, discount_total:int, shipping_total:int, total:int, item_count:int, meets_minimum:bool, minimum_missing:int, shipping_method:?ShippingMethod, display:array{subtotal:int, shipping_total:int, total:int}} $totals */
    public function __construct(Cart $cart, private readonly array $totals)
    {
        parent::__construct($cart);
    }

    public function toArray(Request $request): array
    {
        $currency = app(CurrencyContext::class);
        $display = $this->totals['display'];

        return [
            'items' => $this->items->map(function (CartItem $item) use ($currency) {
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
                    'unit_price' => $currency->format($variant->effectivePrice()),
                    'line_total' => $currency->formatConverted($currency->convert($variant->effectivePrice()) * $item->quantity),
                    'available' => $product->isPublished() && $variant->isPurchasable(),
                ];
            })->values(),
            'item_count' => $this->totals['item_count'],
            'subtotal' => $currency->formatConverted($display['subtotal']),
            'shipping_total' => $display['shipping_total'] > 0 ? $currency->formatConverted($display['shipping_total']) : null,
            'total' => $currency->formatConverted($display['total']),
            'meets_minimum' => $this->totals['meets_minimum'],
            'minimum_missing' => $this->totals['minimum_missing'] > 0 ? $currency->formatConverted(max(1, $currency->convert($this->totals['minimum_missing']))) : null,
            'free_shipping' => $this->freeShipping(),
        ];
    }

    /**
     * Free-shipping progress for the default delivery method: how much more to spend, and % of the way there.
     *
     * @return array{remaining:?string, progress:int}|null null when the method has no free-shipping threshold
     */
    private function freeShipping(): ?array
    {
        $method = $this->totals['shipping_method'];
        if ($method === null || $method->free_over === null || $method->free_over <= 0 || $method->cost === 0) {
            return null;
        }

        $subtotal = $this->totals['subtotal'];
        $remaining = max(0, $method->free_over - $subtotal);

        return [
            'remaining' => $remaining > 0 ? app(CurrencyContext::class)->format($remaining) : null,
            'progress' => (int) min(100, floor($subtotal * 100 / $method->free_over)),
        ];
    }
}
