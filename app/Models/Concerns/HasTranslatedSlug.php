<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * For *Translation models with name/slug/locale columns.
 * Slugs are unique per locale. English slugs are ASCII; Arabic/Hebrew slugs keep their script
 * including combining marks (shadda, tanween…) so legacy WooCommerce URLs survive byte-for-byte.
 */
trait HasTranslatedSlug
{
    public static function bootHasTranslatedSlug(): void
    {
        static::saving(function (Model $translation): void {
            $locale = (string) $translation->locale;
            $source = $translation->slug ?: $translation->name;

            if ($translation->exists && ! $translation->isDirty(['slug', 'name'])) {
                return;
            }

            $translation->slug = static::uniqueSlug((string) $source, $locale, $translation->getKey());
        });
    }

    public static function makeSlug(string $value, string $locale): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $slug = $locale === 'en'
            ? Str::slug($value)
            : trim((string) preg_replace('/[^\pL\pM\pN]+/u', '-', mb_strtolower($value)), '-');

        $slug = rtrim(mb_substr($slug, 0, 150), '-');

        return $slug !== '' ? $slug : Str::lower(Str::random(8));
    }

    protected static function uniqueSlug(string $value, string $locale, mixed $ignoreId = null): string
    {
        $base = static::makeSlug($value, $locale);
        $slug = $base;
        $i = 2;

        while (static::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
