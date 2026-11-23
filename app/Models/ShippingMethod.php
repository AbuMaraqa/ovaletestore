<?php

namespace App\Models;

use App\Enums\ShippingMethodType;
use App\Models\Concerns\HasTranslations;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $code stable key used by ShippingMethodSeeder (inside, west_bank, jerusalem, pickup)
 * @property ShippingMethodType $type
 * @property int $cost minor units
 * @property int|null $free_over minor units
 * @property int|null $min_order minor units
 * @property bool $is_active
 * @property int $sort_order
 * @property-read string $name
 * @property-read string|null $description
 */
class ShippingMethod extends Model implements TranslatableContract
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatedAttributes = ['name', 'description'];

    protected $fillable = ['code', 'type', 'cost', 'free_over', 'min_order', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'type' => ShippingMethodType::class,
            'cost' => 'integer',
            'free_over' => 'integer',
            'min_order' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** Offered for this cart subtotal? */
    public function isAvailableFor(int $subtotal): bool
    {
        return $this->is_active && ($this->min_order === null || $subtotal >= $this->min_order);
    }

    /** Cost for this subtotal (free above the threshold). */
    public function costFor(int $subtotal): int
    {
        return $this->free_over !== null && $subtotal >= $this->free_over ? 0 : $this->cost;
    }
}
