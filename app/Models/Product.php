<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use RalphJSmit\Laravel\SEO\Support\SEOData;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sitemap\Contracts\Sitemapable;

/**
 * Translated attributes (Astrotomic, current locale with fallback):
 *
 * @property-read string $name
 * @property-read string $slug
 * @property-read string|null $short_description
 * @property-read string|null $description
 * @property-read string|null $meta_title
 * @property-read string|null $meta_description
 */
class Product extends Model implements HasMedia, Sitemapable, TranslatableContract
{
    use HasOptimizedImages, HasSeoPage, HasTranslations, RecordsActivity, SoftDeletes;

    /** @var list<string> */
    public array $translatedAttributes = ['name', 'slug', 'short_description', 'description', 'meta_title', 'meta_description'];

    protected $fillable = ['type', 'status', 'brand_id', 'is_featured', 'sort_order', 'source_id', 'published_at'];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function pageUrl(string $locale): string
    {
        return $this->localizedRoute($locale, 'storefront.product', ['slug' => (string) $this->translate($locale, true)?->getAttribute('slug')]);
    }

    /** laravel-seo: the product page head in the current locale (admin meta fields first, then the content). */
    protected function seoDefaults(): SEOData
    {
        [$image, $imageMeta] = SeoImage::fromMedia($this->getFirstMedia('gallery')) ?? [null, null];

        return new SEOData(
            title: $this->meta_title ?: $this->name,
            description: $this->meta_description ?: ($this->short_description ?: $this->description),
            image: $image,
            imageMeta: $imageMeta,
            type: 'product',
        );
    }

    protected function sitemapPriority(): float
    {
        return 0.6;
    }

    /** @return list<string> up to 10 gallery images (1600 px WebP when generated) for the image sitemap */
    protected function sitemapImages(): array
    {
        return $this->getMedia('gallery')->take(10)
            ->map(fn (Media $m) => $m->hasGeneratedConversion('large') ? $m->getUrl('large') : $m->getUrl())
            ->values()->all();
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('is_primary');
    }

    public function productAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class)->orderBy('sort_order');
    }

    /** Custom (product-local) attributes owned by this product. */
    public function customAttributes(): HasMany
    {
        return $this->hasMany(Attribute::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    /** @return HasOne<ProductVariant, $this> */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Published)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isPublished(): bool
    {
        return $this->status === ProductStatus::Published
            && ($this->published_at === null || $this->published_at->isPast());
    }

    public function isVariable(): bool
    {
        return $this->type === ProductType::Variable;
    }

    public function mainImage(): ?Media
    {
        return $this->getFirstMedia('gallery');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')->acceptsMimeTypes(self::acceptedImageMimes());
    }

    protected function optimizedImageCollections(): array
    {
        return ['gallery'];
    }
}
