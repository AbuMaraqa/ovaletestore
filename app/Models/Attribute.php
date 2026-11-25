<?php

namespace App\Models;

use App\Enums\AttributeDisplayType;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsActivity;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $name
 * @property AttributeDisplayType $display_type
 *
 * Global attribute (product_id = null, reusable across products) or a custom attribute owned by one product.
 */
class Attribute extends Model implements TranslatableContract
{
    use HasTranslations, RecordsActivity;

    /** @var list<string> */
    public array $translatedAttributes = ['name'];

    protected $fillable = ['product_id', 'code', 'display_type', 'sort_order'];

    protected function casts(): array
    {
        return ['display_type' => AttributeDisplayType::class];
    }

    /** @return HasMany<AttributeTerm, $this> */
    public function terms(): HasMany
    {
        return $this->hasMany(AttributeTerm::class)->orderBy('sort_order')->orderBy('id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeGlobal(Builder $query): void
    {
        $query->whereNull('product_id');
    }

    public function isGlobal(): bool
    {
        return $this->product_id === null;
    }

    /** A product-specific attribute belongs to that product's history. */
    public function activityParent(): ?array
    {
        return ($this->attributes['product_id'] ?? null) ? [Product::class, $this->attributes['product_id']] : null;
    }
}
