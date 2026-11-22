<?php

namespace App\Models\Concerns;

use Astrotomic\Translatable\Translatable;

/**
 * Astrotomic Translatable + a per-locale fallback chain (config translatable.fallback_chain).
 * getTranslation() tries: requested locale → chain fallback → config fallback ('ar').
 */
trait HasTranslations
{
    use Translatable;

    protected function getFallbackLocale(?string $locale = null): ?string
    {
        if ($locale !== null && ($next = config("translatable.fallback_chain.{$locale}"))) {
            return $next;
        }

        return config('translatable.fallback_locale');
    }
}
