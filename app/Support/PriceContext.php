<?php

namespace App\Support;

use App\Models\PriceTier;
use App\Models\PriceTierPrice;
use App\Models\ProductVariant;
use App\Models\User;

/**
 * Whose prices are we showing? Resolved once per request (scoped binding) from the signed-in user:
 * an approved trader gets their tier, everyone else (guests, customers, staff, queue jobs) retail.
 *
 * Trader price = the variant's exact tier price, else the tier's % off the regular price — and never
 * more than what a retail customer pays right now (a running sale wins when it is cheaper).
 */
final class PriceContext
{
    private bool $resolved = false;

    private ?PriceTier $tier = null;

    /** @var array<int, int>|null variant id => exact tier price */
    private ?array $exact = null;

    public function tier(): ?PriceTier
    {
        if (! $this->resolved) {
            $this->resolved = true;
            $user = auth()->user();
            $this->tier = $user instanceof User ? $user->activePriceTier() : null;
        }

        return $this->tier;
    }

    /** Price as someone on this tier (tests, previews, or pricing on behalf of a trader). */
    public function useTier(?PriceTier $tier): self
    {
        $this->resolved = true;
        $this->tier = $tier;
        $this->exact = null;

        return $this;
    }

    public function priceFor(ProductVariant $variant, int $retail): int
    {
        $tier = $this->tier();
        if ($tier === null) {
            return $retail;
        }

        $this->exact ??= PriceTierPrice::query()->where('price_tier_id', $tier->id)->pluck('price', 'product_variant_id')->all();
        $tierPrice = $this->exact[$variant->id] ?? $tier->discounted($variant->regular_price);

        return min($retail, $tierPrice);
    }
}
