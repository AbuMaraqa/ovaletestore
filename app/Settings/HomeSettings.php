<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Homepage content blocks. Image values are public-disk paths of optimized WebP files
 * (originals kept under home/originals). Plain comments on purpose: laravel-settings derives casts from @var generics.
 */
class HomeSettings extends Settings
{
    // width => path, e.g. ['960' => 'home/hero-960.webp', '1920' => 'home/hero-1920.webp']
    public array $hero_image;

    // locale => text
    public array $hero_title;

    // locale => text
    public array $hero_subtitle;

    // list of paths (brand logos strip)
    public array $brand_logos;

    // list of paths (spotlight collage)
    public array $spotlight_images;

    // locale => text
    public array $spotlight_title;

    // locale => text
    public array $spotlight_text;

    public ?int $spotlight_category_id;

    public static function group(): string
    {
        return 'home';
    }
}
