<?php

namespace App\Models;

use App\Models\Concerns\HasOptimizedImages;
use App\Models\Concerns\HasSeoPage;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsActivity;
use App\Support\Seo\SeoImage;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RalphJSmit\Laravel\SEO\Support\SEOData;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sitemap\Contracts\Sitemapable;

/**
 * Translated attributes (Astrotomic, current locale with fallback):
 *
 * @property-read string $name
 * @property-read string $slug
 * @property-read string|null $description
 */
class Brand extends Model implements HasMedia, Sitemapable, TranslatableContract
{
    use HasOptimizedImages, HasSeoPage, HasTranslations, RecordsActivity;

    /** @var list<string> */
    public array $translatedAttributes = ['name', 'slug', 'description'];

    protected $fillable = ['sort_order', 'is_active', 'is_featured'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_featured' => 'boolean'];
    }

    public function pageUrl(string $locale): string
    {
        return $this->localizedRoute($locale, 'storefront.brand', ['slug' => (string) $this->translate($locale, true)?->getAttribute('slug')]);
    }

    /** laravel-seo: the brand page head in the current locale (brands have no meta fields: description or a generated line). */
    protected function seoDefaults(): SEOData
    {
        [$image, $imageMeta] = SeoImage::fromMedia($this->getFirstMedia('logo')) ?? [null, null];

        return new SEOData(
            title: $this->name,
            description: $this->description ?: __('storefront.brand_products', ['brand' => $this->name]),
            image: $image,
            imageMeta: $imageMeta,
        );
    }

    protected function sitemapPriority(): float
    {
        return 0.5;
    }

    /** @return list<string> */
    protected function sitemapImages(): array
    {
        return array_filter([$this->getFirstMediaUrl('logo')]);
    }

    /** @return HasMany<Product, $this> */
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
