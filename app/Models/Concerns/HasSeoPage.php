<?php

namespace App\Models\Concerns;

use App\Models\Seo;
use App\Support\Seo\SeoImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use RalphJSmit\Laravel\SEO\Support\AlternateTag;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use RalphJSmit\Laravel\SEO\Support\SEOData;
use Spatie\Sitemap\Tags\Url;

/**
 * A record with its own public page (product, category, brand): one source for its SEO head
 * (ralphjsmit/laravel-seo) and its sitemap entry (spatie/laravel-sitemap `Sitemapable`).
 *
 *  - seoDefaults() (per model) returns the current-locale title, description, image and type, served to the
 *    package through its getDynamicSEOData() hook;
 *  - the record's `seo` row (App\Models\Seo) adds the language-independent overrides: robots, canonical, image;
 *  - every language version is listed as hreflang alternate (+ x-default → Arabic), in the head and the sitemap.
 *
 * @mixin Model
 */
trait HasSeoPage
{
    use HasSEO;

    /** Absolute URL of this record's page in a locale. */
    abstract public function pageUrl(string $locale): string;

    /** Current-locale SEO text and image of this record. */
    abstract protected function seoDefaults(): SEOData;

    /** Kept for the duration of one seoData() call: the package's merge drops `imageMeta`, we put it back. */
    private ?SEOData $seoDynamic = null;

    /** laravel-seo hook: called by the `seo` row (prepareForUsage) and merged with its overrides. */
    public function getDynamicSEOData(): SEOData
    {
        return $this->seoDynamic ??= $this->seoDefaults();
    }

    /**
     * The package's relation (HasSEO::seo), typed with our model (config seo.model = App\Models\Seo).
     * An empty Seo instance stands in for records created before laravel-seo was installed.
     *
     * @return MorphOne<Seo, $this>
     */
    public function seo(): MorphOne
    {
        return $this->morphOne(Seo::class, 'model')->withDefault();
    }

    protected static function bootHasSeoPage(): void
    {
        // HasSEO creates the row on `created`; remove it with the record (soft deletes keep it).
        static::deleted(function (self $model): void {
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                $model->seo()->delete();
            }
        });
    }

    /** The merged SEO data (dynamic data first, then the `seo` row) for the current locale. */
    public function seoData(): SEOData
    {
        $row = $this->seo;
        $row->setRelation('model', $this); // no extra query back to this record

        $this->seoDynamic = null;
        $dynamic = $this->getDynamicSEOData(); // memoized: prepareForUsage() below reuses it
        $data = $row->prepareForUsage();
        $data->imageMeta = $dynamic->imageMeta; // the package's merge drops it
        $this->seoDynamic = null;
        $data->alternates = $this->seoAlternates();
        $data->url = $this->pageUrl(app()->getLocale());

        if ($row->image) { // the admin's share image wins over the gallery image
            $data->image = Storage::disk('public')->url($row->image);
            $data->imageMeta = SeoImage::meta($data->image, Storage::disk('public')->path($row->image));
        }

        return $data;
    }

    /** @return list<AlternateTag> every language version + x-default (Arabic). */
    public function seoAlternates(): array
    {
        $tags = array_map(fn (string $locale) => new AlternateTag($locale, $this->pageUrl($locale)), self::seoLocales());
        $tags[] = new AlternateTag('x-default', $this->pageUrl('ar'));

        return $tags;
    }

    /** spatie/laravel-sitemap `Sitemapable`: this record's entry in the current-locale sitemap. */
    public function toSitemapTag(): Url
    {
        $url = Url::create($this->pageUrl(app()->getLocale()))
            ->setPriority($this->sitemapPriority())
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY);

        $updated = $this->getAttribute('updated_at');
        if ($updated instanceof Carbon) {
            $url->setLastModificationDate($updated);
        }

        foreach (self::seoLocales() as $locale) {
            $url->addAlternate($this->pageUrl($locale), $locale);
        }
        $url->addAlternate($this->pageUrl('ar'), 'x-default');

        foreach ($this->sitemapImages() as $image) {
            $url->addImage($image);
        }

        return $url;
    }

    /** Records that asked search engines to stay away are left out of the sitemap too. */
    public function isIndexable(): bool
    {
        return ! str_contains((string) $this->seo->robots, 'noindex') && ! $this->seo->canonical_url;
    }

    protected function sitemapPriority(): float
    {
        return 0.6;
    }

    /** @return list<string> absolute image URLs for the image sitemap */
    protected function sitemapImages(): array
    {
        return [];
    }

    /** Localized absolute URL of a storefront route. @param array<string, mixed> $parameters */
    protected function localizedRoute(string $locale, string $route, array $parameters): string
    {
        return (string) LaravelLocalization::getLocalizedURL($locale, route($route, $parameters), [], false);
    }

    /** @return list<string> */
    private static function seoLocales(): array
    {
        return array_keys(LaravelLocalization::getSupportedLocales());
    }
}
