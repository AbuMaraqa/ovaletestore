<?php

namespace App\Support\Seo;

use App\Settings\GeneralSettings;
use App\Settings\SeoSettings;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * Page-level SEO payload. Rendered server-side in app.blade.php (crawlers need no JS)
 * and kept in sync on client navigation by <SeoHead>.
 *
 * @implements Arrayable<string, mixed>
 */
final class SeoData implements Arrayable
{
    /** @var array<string, string> hreflang => absolute url */
    private array $alternates = [];

    /** @var list<array<string, mixed>> */
    private array $jsonLd = [];

    private string $robots = 'index,follow,max-image-preview:large';

    private string $ogType = 'website';

    private ?string $image = null;

    private ?string $canonical = null;

    private function __construct(private string $title, private ?string $description) {}

    public static function make(string $title, ?string $description = null): self
    {
        return new self($title, $description);
    }

    /**
     * Canonical = current locale URL; alternates for every supported locale + x-default (Arabic).
     *
     * @param  callable(string $locale): ?string  $urlForLocale  null when the page has no version in that locale
     */
    public function localized(callable $urlForLocale): self
    {
        foreach (array_keys(LaravelLocalization::getSupportedLocales()) as $locale) {
            if ($url = $urlForLocale($locale)) {
                $this->alternates[$locale] = $url;
            }
        }

        $this->canonical = $this->alternates[app()->getLocale()] ?? null;
        if (isset($this->alternates['ar'])) {
            $this->alternates['x-default'] = $this->alternates['ar'];
        }

        return $this;
    }

    /** For pages whose URL is the same route in each locale (home, shop…). */
    public function forCurrentRoute(array $keepQuery = []): self
    {
        $query = array_filter(request()->only($keepQuery), fn ($v) => $v !== null && $v !== '' && $v !== '1');

        return $this->localized(function (string $locale) use ($query) {
            $url = LaravelLocalization::getLocalizedURL($locale, request()->url(), [], false);

            return $query ? $url.'?'.http_build_query($query) : $url;
        });
    }

    public function noindex(): self
    {
        $this->robots = 'noindex,follow';

        return $this;
    }

    public function type(string $ogType): self
    {
        $this->ogType = $ogType;

        return $this;
    }

    public function image(?string $absoluteUrl): self
    {
        $this->image = $absoluteUrl;

        return $this;
    }

    /** @param array<string, mixed> $schema */
    public function withJsonLd(array $schema): self
    {
        $this->jsonLd[] = $schema;

        return $this;
    }

    public function toArray(): array
    {
        $general = app(GeneralSettings::class);
        $seo = app(SeoSettings::class);
        $siteName = $general->siteName();
        $locale = app()->getLocale();

        $title = Str::of($this->title)->squish()->limit(70, '')->toString();

        return [
            'title' => $title === $siteName || str_contains($title, $siteName) ? $title : "{$title} | {$siteName}",
            'description' => $this->description ? Str::of(strip_tags($this->description))->squish()->limit(160)->toString() : null,
            'canonical' => $this->canonical ?? url()->current(),
            'robots' => $seo->allow_indexing ? $this->robots : 'noindex,nofollow',
            'alternates' => collect($this->alternates)->map(fn ($url, $lang) => ['hreflang' => $lang, 'href' => $url])->values()->all(),
            'og' => [
                'type' => $this->ogType,
                'site_name' => $siteName,
                'locale' => LaravelLocalization::getSupportedLocales()[$locale]['og'] ?? $locale,
                'image' => $this->image ?? ($seo->og_image_path ? asset('storage/'.$seo->og_image_path) : null),
            ],
            'json_ld' => $this->jsonLd,
        ];
    }
}
