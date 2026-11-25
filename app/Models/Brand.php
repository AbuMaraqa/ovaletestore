<?php

namespace App\Models;

use App\Models\Concerns\HasOptimizedImages;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsActivity;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;

/**
 * Translated attributes (Astrotomic, current locale with fallback):
 *
 * @property-read string $name
 * @property-read string $slug
 * @property-read string|null $description
 */
class Brand extends Model implements HasMedia, TranslatableContract
{
    use HasOptimizedImages, HasTranslations, RecordsActivity;

    /** @var list<string> */
    public array $translatedAttributes = ['name', 'slug', 'description'];

    protected $fillable = ['sort_order', 'is_active', 'is_featured'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_featured' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile()->acceptsMimeTypes(self::acceptedImageMimes());
    }

    protected function optimizedImageCollections(): array
    {
        return ['logo'];
    }
}
