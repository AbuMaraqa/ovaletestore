<?php

namespace App\Support;

use App\Settings\StoreSettings;
use NumberFormatter;

final class Money
{
    public static function format(int $minor, ?string $currency = null, ?string $locale = null): string
    {
        $currency ??= self::storeCurrency();
        $locale ??= app()->getLocale();

        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency($minor / 100, $currency);
    }

    /** The currency chosen in Admin → Settings → Store (orders snapshot the same value); ILS before settings exist. */
    public static function storeCurrency(): string
    {
        try {
            return app(StoreSettings::class)->currency ?: 'ILS';
        } catch (\Throwable) {
            return 'ILS'; // settings table not migrated yet (installs, early console commands)
        }
    }

    /** Converts user input like "12.5" to minor units (1250) without float drift. */
    public static function toMinor(string|int|float|null $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return (int) round(((float) $amount) * 100);
    }

    public static function toMajor(?int $minor): ?string
    {
        return $minor === null ? null : number_format($minor / 100, 2, '.', '');
    }
}
