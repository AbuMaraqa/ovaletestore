<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * UI strings for Vue come from Laravel's own lang files (single source of truth).
 * Storefront visitors receive only storefront groups; staff pages also receive admin strings.
 */
final class Translations
{
    private const GROUPS = [
        'storefront' => ['common', 'storefront', 'cart', 'http-statuses'],
        'admin' => ['common', 'admin', 'orders'],
    ];

    /** @return array<string, mixed> */
    public static function forFrontend(string $locale, string $area): array
    {
        $loader = fn () => collect(self::GROUPS[$area])
            ->mapWithKeys(fn (string $group) => [$group => trans($group, [], $locale)])
            ->filter(fn ($lines) => is_array($lines))
            ->all();

        return app()->isProduction()
            ? Cache::rememberForever("ui-translations.{$area}.{$locale}", $loader)
            : $loader();
    }
}
