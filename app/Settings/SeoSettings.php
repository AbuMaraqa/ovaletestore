<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SeoSettings extends Settings
{
    // Plain comments on purpose: laravel-settings derives casts from @var generics.
    // locale => homepage title
    public array $home_title;

    // array<string, string>
    public array $home_description;

    // locale => description used by pages that have none of their own (laravel-seo fallback)
    public array $default_description;

    public ?string $og_image_path;

    /** Global kill-switch (e.g. staging): when false every page is noindex. */
    public bool $allow_indexing;

    /** X (Twitter) account for twitter:site, without "@". */
    public ?string $twitter_username;

    /** Search Console / Bing Webmaster verification codes (the content value only). */
    public ?string $google_verification;

    public ?string $bing_verification;

    /** GA4 web stream measurement id (G-XXXXXXX) — public by nature; the Data API credentials stay in .env. */
    public ?string $ga_measurement_id;

    public static function group(): string
    {
        return 'seo';
    }
}
