<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ContactSettings extends Settings
{
    public ?string $phone;

    /** International format without "+", e.g. 970599000000 (used for wa.me links). */
    public ?string $whatsapp;

    public ?string $email;

    // Plain comments on purpose: laravel-settings derives casts from @var generics.
    // locale => address
    public array $address;

    // network => url|null
    public array $social_links;

    public static function group(): string
    {
        return 'contact';
    }
}
