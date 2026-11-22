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
