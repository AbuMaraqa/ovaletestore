<?php

namespace App\Support;

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

    /** The base (default) currency from Admin → Currencies: every stored amount is in it. */
    public static function storeCurrency(): string
    {
        return app(CurrencyContext::class)->base()->code;
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
