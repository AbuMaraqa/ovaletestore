<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A trader price list ("جملة", "موزع"…): an automatic % off the regular price, overridden per variant
 * by an exact price in price_tier_prices.
 *
 * @property int $id
 * @property string $code
 * @property array<string, string> $name
 * @property string $discount_percent
 * @property bool $is_active
 * @property int $sort_order
 */
class PriceTier extends Model
{
    use RecordsActivity;

    protected $fillable = ['code', 'name', 'discount_percent', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['name' => 'array', 'discount_percent' => 'decimal:2', 'is_active' => 'boolean'];
    }

    /** @return HasMany<PriceTierPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(PriceTierPrice::class);
    }

    /** @return HasMany<User, $this> */
    public function traders(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->name[$locale] ?? $this->name['ar'] ?? $this->code;
    }

    /** The tier's automatic price for a regular price (before any exact per-variant price). */
    public function discounted(int $regularPrice): int
    {
        return (int) round($regularPrice * (100 - (float) $this->discount_percent) / 100);
    }

    public function activityLabel(): string
    {
        return $this->label('ar');
    }
}
