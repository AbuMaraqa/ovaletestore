<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NumberFormatter;

/**
 * A currency the store sells in. Exactly one row is the default (base) currency: all stored prices
 * are in it and its rate is always 1. `rate` = units of this currency per 1 base unit (e.g. base ILS,
 * USD rate 0.27). Amounts everywhere are integer hundredths of the currency's major unit.
 *
 * @property int $id
 * @property string $code ISO 4217
 * @property array<string, string> $name
 * @property string $rate
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort_order
 */
class Currency extends Model
{
    use RecordsActivity;

    protected $fillable = ['code', 'name', 'rate', 'is_active', 'sort_order'];

    protected $attributes = ['rate' => 1, 'is_default' => false, 'is_active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['name' => 'array', 'rate' => 'decimal:8', 'is_default' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('code');
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->name[$locale] ?? $this->name['ar'] ?? $this->code;
    }

    /** The symbol shown for this currency in a locale (₪, $, د.أ.‏). */
    public function symbol(?string $locale = null): string
    {
        $formatter = new NumberFormatter(($locale ?? app()->getLocale()).'@currency='.$this->code, NumberFormatter::CURRENCY);

        return (string) $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
    }

    /** Base amount (minor units) → this currency (minor units). */
    public function fromBase(int $minor): int
    {
        return $this->is_default || (float) $this->rate <= 0 ? $minor : (int) round($minor * (float) $this->rate);
    }

    /** This currency (minor units) → base amount (minor units). */
    public function toBase(int $minor): int
    {
        return $this->is_default || (float) $this->rate <= 0 ? $minor : (int) round($minor / (float) $this->rate);
    }

    public function activityLabel(): string
    {
        return $this->code;
    }

    /** Never logged as a field change: the default flag only moves through SetBaseCurrency (logged there). */
    protected function activityAttributes(): array
    {
        return ['code', 'name', 'rate', 'is_active', 'sort_order'];
    }
}
