<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    // Plain comments on purpose: laravel-settings derives casts from @var generics.
    // locale => value
    public array $site_name;

    // array<string, string>
    public array $tagline;

    /** Public-disk relative paths (uploaded through the admin, validated images). */
    public ?string $logo_path;

    public ?string $favicon_path;

    public string $default_locale;

    // list<string>
    public array $enabled_locales;

    /** 'tajawal' (bundled) or 'custom' (uploaded files below). See App\Support\SiteFont. */
    public string $font_family;

    /** CSS family name of the custom font, e.g. "OT Magnit Arabic". */
    public ?string $font_name;

    // weight (400/500/700) => public-disk path of the uploaded font file
    public array $font_files;

    /** 'all' (store + admin) or 'storefront'. */
    public string $font_scope;

    // list<string>: site languages that use the custom font (others keep Tajawal)
    public array $font_locales;

    public static function group(): string
    {
        return 'general';
    }

    public function siteName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->site_name[$locale] ?? $this->site_name['ar'] ?? config('app.name');
    }
}
