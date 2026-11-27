<?php

namespace App\Providers;

use App\Settings\GeneralSettings;
use App\Settings\SeoSettings;
use App\Support\Seo\SeoImage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use RalphJSmit\Laravel\SEO\Facades\SEOManager;
use RalphJSmit\Laravel\SEO\Support\MetaTag;
use RalphJSmit\Laravel\SEO\Support\OpenGraphTag;
use RalphJSmit\Laravel\SEO\Support\SEOData;
use RalphJSmit\Laravel\SEO\TagCollection;

/**
 * Site-wide SEO rules for ralphjsmit/laravel-seo. config/seo.php is static; these values are edited by the
 * owner in Admin → Settings → SEO, so they are applied at runtime as transformers on every page.
 */
class SeoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        SEOManager::SEODataTransformer(function (SEOData $data): SEOData {
            $seo = app(SeoSettings::class);
            $siteName = app(GeneralSettings::class)->siteName();
            $locale = app()->getLocale();

            // Title: squished, ≤ 70 chars, then " | Site name" (unless the page opted out or already says it).
            $title = Str::of((string) $data->title)->stripTags()->squish()->limit(70, '')->toString();
            $data->title = $title === '' ? $siteName
                : ($data->enableTitleSuffix && ! str_contains($title, $siteName) ? "{$title} | {$siteName}" : $title);

            // Description: plain text ≤ 160 chars; fallback = the default description of this language.
            $description = $data->description ?: ($seo->default_description[$locale] ?? null);
            $data->description = $description
                ? Str::of(html_entity_decode(strip_tags($description)))->squish()->limit(160)->toString() ?: null
                : null;

            $data->site_name = $siteName;
            $data->locale = LaravelLocalization::getSupportedLocales()[$locale]['og'] ?? $locale;
            $data->twitter_username = $seo->twitter_username ? '@'.ltrim($seo->twitter_username, '@') : null;

            // Share image fallback: Settings → SEO image (measured, for og:image:width/height + the right Twitter card).
            if (! $data->image && $seo->og_image_path) {
                $data->image = asset('storage/'.$seo->og_image_path);
                $data->imageMeta = SeoImage::meta($data->image, storage_path('app/public/'.$seo->og_image_path));
            }

            // Global kill-switch (staging, pre-launch): nothing is indexed, whatever the page asked for.
            $data->robots = $seo->allow_indexing ? ($data->robots ?? config('seo.robots.default')) : 'noindex,nofollow';

            return $data;
        });

        SEOManager::tagTransformer(function (TagCollection $tags): TagCollection {
            $seo = app(SeoSettings::class);

            // Search Console / Bing Webmaster ownership (the code only, never a full tag).
            foreach (['google-site-verification' => $seo->google_verification, 'msvalidate.01' => $seo->bing_verification] as $name => $code) {
                if ($code) {
                    $tags->push(new MetaTag($name, $code));
                }
            }

            // og:locale:alternate for the other languages of the store.
            $current = app()->getLocale();
            foreach (LaravelLocalization::getSupportedLocales() as $locale => $info) {
                if ($locale !== $current) {
                    $tags->push(new OpenGraphTag('locale:alternate', $info['og'] ?? $locale));
                }
            }

            return $tags;
        });
    }
}
