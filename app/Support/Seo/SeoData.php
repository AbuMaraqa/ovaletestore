<?php

namespace App\Support\Seo;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Collection;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use RalphJSmit\Laravel\SEO\Facades\SEOManager;
use RalphJSmit\Laravel\SEO\Schema\BreadcrumbListSchema;
use RalphJSmit\Laravel\SEO\SchemaCollection;
use RalphJSmit\Laravel\SEO\Support\AlternateTag;
use RalphJSmit\Laravel\SEO\Support\SEOData as PackageSeoData;
use RalphJSmit\Laravel\SEO\Support\Tag;

/**
 * The `seo` page prop: a page's <head> built with ralphjsmit/laravel-seo and handed to Inertia v3's
 * server-head (`createInertiaApp({ serverHead: 'seo' })`).
 *
 *  - The package builds every tag: title, description, robots, canonical, hreflang alternates, sitemap link,
 *    Open Graph (+ image size), Twitter card, JSON-LD (its BreadcrumbList + our spatie/schema-org objects).
 *  - Site-wide rules (title suffix, fallbacks, the indexing kill-switch, verification tags) are SEOData/tag
 *    transformers in App\Providers\SeoServiceProvider, so they apply to every page in one place.
 *  - Each tag gets a stable `data-inertia` key: app.blade.php prints them for crawlers (no JS needed) and
 *    Inertia swaps exactly those tags on client-side navigation — no stale or duplicate tags.
 *
 * Usage: `'seo' => SeoData::for($product)->breadcrumbs([...])->withJsonLd(JsonLd::product(...))`
 *        `'seo' => SeoData::make(__('cart.title'))->noindex()`
 *
 * @implements Arrayable<int, string>
 */
final class SeoData implements Arrayable
{
    private SchemaCollection $schema;

    private function __construct(private PackageSeoData $data)
    {
        $this->schema = $data->schema ?? SchemaCollection::initialize();
    }

    public static function make(string $title, ?string $description = null): self
    {
        return new self(new PackageSeoData(title: $title, description: $description));
    }

    /** A record's own page: its laravel-seo data (translations + `seo` row overrides), URL and alternates. */
    public static function for(Product|Category|Brand $model): self
    {
        return new self($model->seoData());
    }

    /**
     * Canonical = this locale's URL; one alternate per language that has the page + x-default (Arabic).
     *
     * @param  callable(string $locale): ?string  $urlForLocale  null when the page has no version in that locale
     */
    public function localized(callable $urlForLocale): self
    {
        $alternates = [];
        foreach (array_keys(LaravelLocalization::getSupportedLocales()) as $locale) {
            if ($url = $urlForLocale($locale)) {
                $alternates[$locale] = $url;
            }
        }
        if (isset($alternates['ar'])) {
            $alternates['x-default'] = $alternates['ar'];
        }

        $this->data->url = $alternates[app()->getLocale()] ?? $this->data->url;
        $this->data->alternates = array_map(
            fn (string $url, string $lang) => new AlternateTag($lang, $url), $alternates, array_keys($alternates),
        );

        return $this;
    }

    /** For pages whose URL is the same route in each locale (home, shop…). @param list<string> $keepQuery */
    public function forCurrentRoute(array $keepQuery = []): self
    {
        $query = array_filter(request()->only($keepQuery), fn ($v) => $v !== null && $v !== '' && $v !== '1');

        return $this->localized(function (string $locale) use ($query) {
            $url = (string) LaravelLocalization::getLocalizedURL($locale, request()->url(), [], false);

            return $query ? $url.'?'.http_build_query($query) : $url;
        });
    }

    /** Self-referencing canonical for page 2+ of a listing (Google: each page is its own canonical). */
    public function page(int $page): self
    {
        if ($page > 1 && $this->data->url) {
            $this->data->url .= '?page='.$page;
            foreach ($this->data->alternates ?? [] as $tag) {
                $tag->attributes['href'] .= '?page='.$page;
            }
        }

        return $this;
    }

    /** Thin or duplicate content (search results, filters, cart…): keep out of the index, still follow links. */
    public function noindex(): self
    {
        $this->data->robots = 'noindex,follow';

        return $this;
    }

    public function type(string $ogType): self
    {
        $this->data->type = $ogType;

        return $this;
    }

    public function image(?string $absoluteUrl): self
    {
        $this->data->image = $absoluteUrl;

        return $this;
    }

    /** The home page shows its own title without the " | Site" suffix. */
    public function withoutTitleSuffix(): self
    {
        $this->data->enableTitleSuffix = false;

        return $this;
    }

    /** schema.org BreadcrumbList (laravel-seo markup). @param list<array{name: string, url: string}> $items */
    public function breadcrumbs(array $items): self
    {
        $this->schema->addBreadcrumbs(function (BreadcrumbListSchema $schema) use ($items): BreadcrumbListSchema {
            $schema->breadcrumbs = collect($items)->mapWithKeys(fn (array $item) => [$item['name'] => $item['url']]);

            return $schema;
        });

        return $this;
    }

    /** Any other schema.org object (spatie/schema-org ->toArray()). @param array<string, mixed> $schema */
    public function withJsonLd(array $schema): self
    {
        $this->schema->push($schema);

        return $this;
    }

    /** @return list<string> the <head> tags, each with its `data-inertia` key */
    public function toArray(): array
    {
        $this->data->schema = $this->schema->isEmpty() && $this->schema->markup === [] ? null : $this->schema;

        $tags = seo($this->data)->tags->pipeThrough(SEOManager::getTagTransformers());

        $seen = [];

        return collect(self::flatten($tags))->map(function (Tag $tag) use (&$seen): string {
            unset($tag->attributes['inertia'], $tag->attributes['data-inertia']);
            $key = self::key($tag);
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $tag->attributes['data-inertia'] = $seen[$key] > 1 ? "{$key}:{$seen[$key]}" : $key;

            return trim($tag->render()->render());
        })->values()->all();
    }

    /** The package nests collections (Open Graph, Twitter card…); Inertia needs one string per element. @return list<Tag> */
    private static function flatten(iterable $items): array
    {
        $flat = [];
        foreach ($items as $item) {
            if ($item instanceof Tag) {
                $flat[] = $item;
            } elseif ($item instanceof Collection || is_array($item)) {
                array_push($flat, ...self::flatten($item));
            } elseif ($item instanceof Renderable) {
                report(new \LogicException('Unexpected SEO tag type '.$item::class));
            }
        }

        return $flat;
    }

    /** Stable identity of a head element, so Inertia replaces "the description" instead of appending another. */
    private static function key(Tag $tag): string
    {
        $a = $tag->collectAttributes();

        return match (true) {
            $tag->tag === 'title' => 'title',
            $tag->tag === 'script' => 'ld-json',
            $a->has('hreflang') => 'alternate-'.$a['hreflang'],
            $a->has('rel') => (string) $a['rel'],
            $a->has('property') => (string) $a['property'],
            default => 'meta-'.$a->get('name', $tag->tag),
        };
    }
}
