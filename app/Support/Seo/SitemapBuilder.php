<?php

namespace App\Support\Seo;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;

/**
 * Only indexable, canonical URLs: no admin, cart, checkout, search, filtered or paginated variants.
 * Every URL lists its hreflang alternates (incl. x-default → Arabic).
 */
class SitemapBuilder
{
    public const TYPES = ['pages', 'categories', 'products'];

    public static function flush(): void
    {
        Cache::forget('sitemap:index');
        foreach (self::locales() as $locale) {
            foreach (self::TYPES as $type) {
                Cache::forget("sitemap:{$locale}:{$type}");
            }
        }
    }

    public function index(): SitemapIndex
    {
        $index = SitemapIndex::create();

        foreach (self::locales() as $locale) {
            foreach (self::TYPES as $type) {
                $index->add(route('sitemap.show', ['locale' => $locale, 'type' => $type]));
            }
        }

        return $index;
    }

    public function build(string $locale, string $type): Sitemap
    {
        $sitemap = Sitemap::create();

        match ($type) {
            'pages' => $this->pages($sitemap, $locale),
            'categories' => $this->categories($sitemap, $locale),
            'products' => $this->products($sitemap, $locale),
        };

        return $sitemap;
    }

    private function pages(Sitemap $sitemap, string $locale): void
    {
        foreach (['storefront.home' => 1.0, 'storefront.shop' => 0.8] as $route => $priority) {
            $sitemap->add($this->url(fn (string $l) => $this->localized($l, route($route)), $locale)->setPriority($priority));
        }
    }

    private function categories(Sitemap $sitemap, string $locale): void
    {
        Category::query()->active()->with('translations')
            ->whereHas('products', fn ($q) => $q->published())
            ->each(function (Category $category) use ($sitemap, $locale) {
                $sitemap->add(
                    $this->url(fn (string $l) => $this->localized($l, route('storefront.category', ['slug' => $category->translate($l, true)->slug])), $locale)
                        ->setLastModificationDate($category->updated_at)
                        ->setPriority(0.7),
                );
            });
    }

    private function products(Sitemap $sitemap, string $locale): void
    {
        Product::query()->published()->with(['translations', 'media'])->chunkById(200, function ($products) use ($sitemap, $locale) {
            foreach ($products as $product) {
                $url = $this->url(fn (string $l) => $this->localized($l, route('storefront.product', ['slug' => $product->translate($l, true)->slug])), $locale)
                    ->setLastModificationDate($product->updated_at)
                    ->setPriority(0.6);

                if ($image = $product->getFirstMedia('gallery')) {
                    $url->addImage($image->hasGeneratedConversion('large') ? $image->getUrl('large') : $image->getUrl(), (string) $product->translate($locale, true)->name);
                }

                $sitemap->add($url);
            }
        });
    }

    /** @param callable(string): string $urlFor */
    private function url(callable $urlFor, string $locale): Url
    {
        $url = Url::create($urlFor($locale));

        foreach (self::locales() as $alt) {
            $url->addAlternate($urlFor($alt), $alt);
        }
        $url->addAlternate($urlFor('ar'), 'x-default');

        return $url;
    }

    private function localized(string $locale, string $url): string
    {
        return LaravelLocalization::getLocalizedURL($locale, $url, [], false);
    }

    /** @return list<string> */
    private static function locales(): array
    {
        return array_keys(LaravelLocalization::getSupportedLocales());
    }
}
