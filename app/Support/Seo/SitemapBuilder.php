<?php

namespace App\Support\Seo;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Settings\SeoSettings;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Sitemap as SitemapTag;
use Spatie\Sitemap\Tags\Url;

/**
 * spatie/laravel-sitemap: an index (/sitemap.xml) → one sitemap per language × type.
 *
 * Only indexable, canonical URLs: no admin, cart, checkout, search, filtered or paginated variants, and no record
 * whose `seo` row says noindex or points its canonical elsewhere. Records are added as `Sitemapable` models
 * (App\Models\Concerns\HasSeoPage::toSitemapTag): every URL carries its hreflang alternates (+ x-default) and
 * lastmod; products and categories add their images (image sitemap).
 */
class SitemapBuilder
{
    public const TYPES = ['pages', 'categories', 'brands', 'products'];

    private const CACHE_SECONDS = 3600;

    public static function flush(): void
    {
        Cache::forget('sitemap:index');
        foreach (self::locales() as $locale) {
            foreach (self::TYPES as $type) {
                Cache::forget("sitemap:{$locale}:{$type}");
            }
        }
    }

    /** Rendered XML, cached for an hour (and flushed when the catalog changes). */
    public function xml(?string $locale = null, ?string $type = null): string
    {
        if ($locale === null || $type === null) {
            return Cache::remember('sitemap:index', self::CACHE_SECONDS, fn () => $this->index()->render());
        }

        return Cache::remember("sitemap:{$locale}:{$type}", self::CACHE_SECONDS, fn () => $this->build($locale, $type)->render());
    }

    /** The index lists each child sitemap with the date its newest entry changed (crawlers skip unchanged ones). */
    public function index(): SitemapIndex
    {
        $index = SitemapIndex::create();
        if (! app(SeoSettings::class)->allow_indexing) {
            return $index; // kill-switch: nothing to submit
        }

        $lastmod = [
            // Home/shop list the newest products: they change when the catalog does (an honest lastmod; Google ignores fake ones).
            'pages' => $this->lastModified(Product::query()->published()),
            'categories' => $this->lastModified($this->categories()),
            'brands' => $this->lastModified($this->brands()),
            'products' => $this->lastModified(Product::query()->published()),
        ];

        foreach (self::locales() as $locale) {
            foreach (self::TYPES as $type) {
                $tag = SitemapTag::create(route('sitemap.show', ['locale' => $locale, 'type' => $type]));
                if ($lastmod[$type]) {
                    $tag->setLastModificationDate($lastmod[$type]);
                }
                $index->add($tag);
            }
        }

        return $index;
    }

    public function build(string $locale, string $type): Sitemap
    {
        $sitemap = Sitemap::create();
        if (! app(SeoSettings::class)->allow_indexing) {
            return $sitemap;
        }

        // Sitemapable::toSitemapTag() builds the URL for the current locale.
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            match ($type) {
                'pages' => $this->pages($sitemap, $locale),
                'categories' => $this->addModels($sitemap, $this->categories()),
                'brands' => $this->addModels($sitemap, $this->brands()),
                'products' => $this->addModels($sitemap, Product::query()->published()->with(['translations', 'media', 'seo'])),
            };
        } finally {
            app()->setLocale($previous);
        }

        return $sitemap;
    }

    private function pages(Sitemap $sitemap, string $locale): void
    {
        $pages = ['storefront.home' => 1.0, 'storefront.shop' => 0.8, 'storefront.categories' => 0.8, 'storefront.brands' => 0.6,
            'storefront.about' => 0.4, 'storefront.contact' => 0.4];

        foreach ($pages as $route => $priority) {
            $localized = fn (string $l) => (string) LaravelLocalization::getLocalizedURL($l, route($route), [], false);

            $url = Url::create($localized($locale))->setPriority($priority)
                ->setChangeFrequency($route === 'storefront.home' ? Url::CHANGE_FREQUENCY_DAILY : Url::CHANGE_FREQUENCY_WEEKLY);
            foreach (self::locales() as $alt) {
                $url->addAlternate($localized($alt), $alt);
            }
            $sitemap->add($url->addAlternate($localized('ar'), 'x-default'));
        }
    }

    /** Adds Sitemapable records in chunks, skipping those marked noindex / canonicalised elsewhere. @param Builder<Model> $query */
    private function addModels(Sitemap $sitemap, Builder $query): void
    {
        $query->chunkById(200, function ($models) use ($sitemap) {
            $sitemap->add($models->filter(fn (Model $m) => ! method_exists($m, 'isIndexable') || $m->isIndexable())->values()->all());
        });
    }

    /** @return Builder<Category> */
    private function categories(): Builder
    {
        return Category::query()->active()->with(['translations', 'media', 'seo'])
            ->whereHas('products', self::published(...));
    }

    /** @return Builder<Brand> */
    private function brands(): Builder
    {
        return Brand::query()->active()->with(['translations', 'media', 'seo'])
            ->whereHas('products', self::published(...));
    }

    /**
     * whereHas() constraint: only records with at least one published product.
     *
     * @param  Builder<Product>  $query
     */
    private static function published(Builder $query): void
    {
        $query->published();
    }

    /** @param Builder<covariant Model> $query */
    private function lastModified(Builder $query): ?Carbon
    {
        $value = (clone $query)->toBase()->reorder()->max($query->getModel()->qualifyColumn('updated_at'));

        return $value ? Carbon::parse($value) : null;
    }

    /** Warms (rebuilds) every sitemap; used by `sitemap:generate` and after deploys. @param Closure(string): void|null $progress */
    public function warm(?Closure $progress = null): int
    {
        self::flush();
        $this->xml();
        $count = 0;
        foreach (self::locales() as $locale) {
            foreach (self::TYPES as $type) {
                $this->xml($locale, $type);
                $count++;
                $progress && $progress("{$locale}-{$type}");
            }
        }

        return $count;
    }

    /** @return list<string> */
    private static function locales(): array
    {
        return array_keys(LaravelLocalization::getSupportedLocales());
    }
}
