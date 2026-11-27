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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property-read string|null $meta_title
 * @property-read string|null $meta_description
 */
class Category extends Model implements HasMedia, Sitemapable, TranslatableContract
{
    use HasOptimizedImages, HasSeoPage, HasTranslations, RecordsActivity;

    /** @var list<string> */
    public array $translatedAttributes = ['name', 'slug', 'description', 'meta_title', 'meta_description'];

    protected $fillable = ['parent_id', 'sort_order', 'is_active', 'source_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function pageUrl(string $locale): string
    {
        return $this->localizedRoute($locale, 'storefront.category', ['slug' => (string) $this->translate($locale, true)?->getAttribute('slug')]);
    }

    /** laravel-seo: the category page head in the current locale. */
    protected function seoDefaults(): SEOData
    {
        [$image, $imageMeta] = SeoImage::fromMedia($this->getFirstMedia('image')) ?? [null, null];

        return new SEOData(
            title: $this->meta_title ?: $this->name,
            description: $this->meta_description ?: $this->description,
            image: $image,
            imageMeta: $imageMeta,
        );
    }

    protected function sitemapPriority(): float
    {
        return 0.7;
    }

    /** @return list<string> */
    protected function sitemapImages(): array
    {
        return array_filter([$this->getFirstMediaUrl('image')]);
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('is_primary');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /** Ids of this category and all its descendants (catalog filtering by parent category). */
    public function descendantAndSelfIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = self::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
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
