<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Exact trader price of one variant in one tier (minor units).
 *
 * @property int $id
 * @property int $price_tier_id
 * @property int $product_variant_id
 * @property int $price
 */
class PriceTierPrice extends Model
{
    use RecordsActivity;

    protected $fillable = ['price_tier_id', 'product_variant_id', 'price'];

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }

    /** @return BelongsTo<PriceTier, $this> */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class, 'price_tier_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** Trader price edits belong to the product's history. */
    public function activityParent(): ?array
    {
        return [Product::class, ProductVariant::query()->whereKey($this->attributes['product_variant_id'] ?? null)->value('product_id')];
    }
}
