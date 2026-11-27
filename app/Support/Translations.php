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
        'storefront' => ['common', 'storefront', 'cart', 'account', 'orders', 'http-statuses', 'pos', 'invoices'],
        'admin' => ['common', 'admin', 'orders', 'table', 'activity', 'permissions', 'customers', 'traders', 'currencies', 'invoices'],
        'pos' => ['common', 'pos', 'orders', 'cart', 'account', 'invoices'],
    ];

    /** @return array<string, mixed> */
    public static function forFrontend(string $locale, string $area): array
    {
        $loader = fn () => collect(self::GROUPS[$area])
            ->mapWithKeys(fn (string $group) => [$group => trans($group, [], $locale)])
            ->filter(fn ($lines) => is_array($lines))
            ->all();

        return app()->isProduction()
            ? Cache::rememberForever("ui-translations.{$area}.{$locale}.".self::version($locale, $area), $loader)
            : $loader();
    }

    /**
     * Changes whenever one of the area's language files changes (deploys, new keys). It is part of the cache key
     * and of the Inertia once-prop key, so neither the server cache nor an open browser keeps old strings.
     */
    public static function version(string $locale, string $area): string
    {
        $stamp = '';
        foreach (self::GROUPS[$area] as $group) {
            $file = lang_path("{$locale}/{$group}.php");
            $stamp .= $group.(is_file($file) ? (string) filemtime($file) : '0');
        }

        return substr(md5($stamp), 0, 10);
    }
}
