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

    public ?string $og_image_path;

    /** Global kill-switch (e.g. staging): when false every page is noindex. */
    public bool $allow_indexing;

    public static function group(): string
    {
        return 'seo';
    }
}
