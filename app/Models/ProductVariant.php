<?php

namespace App\Models;

use App\Enums\StockStatus;
use App\Models\Concerns\HasOptimizedImages;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;

/**
 * The purchasable unit. Simple products own exactly one variant (signature '').
 * Prices are integer minor units (agorot).
 *
 * @property int $id
 * @property int $product_id
 * @property string|null $sku
 * @property int $regular_price
 * @property int|null $sale_price
 * @property CarbonInterface|null $sale_starts_at
 * @property CarbonInterface|null $sale_ends_at
 * @property StockStatus $stock_status
 * @property bool $manage_stock
 * @property int|null $stock_quantity
 * @property-read Product|null $product null when the product is soft-deleted
 */
class ProductVariant extends Model implements HasMedia
{
    use HasOptimizedImages;

    protected $fillable = [
        'product_id', 'signature', 'sku', 'regular_price', 'sale_price', 'sale_starts_at', 'sale_ends_at',
        'manage_stock', 'stock_quantity', 'stock_status', 'weight_grams', 'length_mm', 'width_mm', 'height_mm',
        'is_active', 'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'regular_price' => 'integer',
            'sale_price' => 'integer',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'manage_stock' => 'boolean',
            'stock_quantity' => 'integer',
            'stock_status' => StockStatus::class,
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(AttributeTerm::class, 'product_variant_term');
    }

    public function isOnSale(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->sale_price !== null
            && $this->sale_price < $this->regular_price
            && ($this->sale_starts_at === null || $this->sale_starts_at->lte($at))
            && ($this->sale_ends_at === null || $this->sale_ends_at->gt($at));
    }

    public function effectivePrice(?CarbonInterface $at = null): int
    {
        return $this->isOnSale($at) ? (int) $this->sale_price : $this->regular_price;
    }

    /** Whole-percent saving for the "-20%" badge; null when not on sale or the saving rounds to 0. */
    public function discountPercent(?CarbonInterface $at = null): ?int
    {
        if (! $this->isOnSale($at) || $this->regular_price <= 0) {
            return null;
        }

        $percent = (int) floor(($this->regular_price - (int) $this->sale_price) * 100 / $this->regular_price);

        return $percent > 0 ? $percent : null;
    }

    public function isPurchasable(): bool
    {
        if (! $this->is_active || ! $this->stock_status->isPurchasable()) {
            return false;
        }

        return ! $this->manage_stock || $this->stock_status === StockStatus::OnBackorder || $this->stock_quantity > 0;
    }

    /** Max quantity a customer may order, null = unlimited. */
    public function availableQuantity(): ?int
    {
        if (! $this->manage_stock || $this->stock_status === StockStatus::OnBackorder) {
            return null;
        }

        return max(0, (int) $this->stock_quantity);
    }

    /** Human label such as "1 kg / Box" in the current locale (requires terms.translations loaded). */
    public function label(): string
    {
        return $this->terms
            ->sortBy(fn (AttributeTerm $term) => [$term->attribute_id, $term->sort_order])
            ->map(fn (AttributeTerm $term) => $term->name)
            ->implode(' / ');
    }

    public static function signatureFor(iterable $termIds): string
    {
        $ids = collect($termIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();

        return $ids->implode('-');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile()->acceptsMimeTypes(self::acceptedImageMimes());
    }

    protected function optimizedImageCollections(): array
    {
        return ['image'];
    }
}
