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
    // network => url|null
    public array $social_links;

    // list of [number, label => [locale => text]] shown in the footer and on the contact page
    public array $phone_lines;

    // list of [name => [locale => text], address => [locale => text], lat, lng] (coordinates optional)
    public array $locations;

    public static function group(): string
    {
        return 'contact';
    }

    /** @return list<array{number:string, label:?string}> */
    public function phoneLinesFor(string $locale): array
    {
        return array_values(array_map(fn (array $line) => [
            'number' => (string) $line['number'],
            'label' => self::localized($line['label'] ?? [], $locale),
        ], $this->phone_lines));
    }

    /**
     * Locations ready for the storefront: localized text + Google Maps embed and directions URLs
     * (by coordinates when set, otherwise by the address text).
     *
     * @return list<array{name:?string, address:?string, map_embed:string, directions:string}>
     */
    public function locationsFor(string $locale): array
    {
        return array_values(array_map(function (array $location) use ($locale) {
            $address = self::localized($location['address'] ?? [], $locale);
            $hasPin = is_numeric($location['lat'] ?? null) && is_numeric($location['lng'] ?? null);
            $query = $hasPin ? $location['lat'].','.$location['lng'] : trim((string) ($location['address']['ar'] ?? $address));

            return [
                'name' => self::localized($location['name'] ?? [], $locale),
                'address' => $address,
                'map_embed' => 'https://www.google.com/maps?'.http_build_query(['q' => $query, 'z' => 16, 'hl' => $locale, 'output' => 'embed']),
                'directions' => 'https://www.google.com/maps/dir/?'.http_build_query(['api' => 1, 'destination' => $query]),
            ];
        }, $this->locations));
    }

    /** Fallback chain he→en→ar, en→ar, ar→en (same as translatable content). */
    private static function localized(array $values, string $locale): ?string
    {
        $chain = match ($locale) {
            'he' => ['he', 'en', 'ar'],
            'en' => ['en', 'ar'],
            default => [$locale, 'ar', 'en'],
        };

        foreach ($chain as $code) {
            if (filled($values[$code] ?? null)) {
                return (string) $values[$code];
            }
        }

        return null;
    }
}
