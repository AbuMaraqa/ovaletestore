<?php

namespace App\Support;

use Astrotomic\Translatable\Contracts\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Applies a validated `translations[locale][field]` payload to an Astrotomic model.
 * Locales whose required field is blank are removed so the fallback chain applies.
 */
final class TranslationInput
{
    /**
     * @param  Model&Translatable  $model
     * @param  array<string, array<string, mixed>>  $translations
     * @param  list<string>  $fields
     * @param  list<string>  $htmlFields  sanitized with RichText
     */
    public static function apply(Model $model, array $translations, array $fields, array $htmlFields = [], string $requiredField = 'name'): void
    {
        foreach (config('translatable.locales') as $locale) {
            $input = $translations[$locale] ?? [];

            if (blank($input[$requiredField] ?? null)) {
                if ($model->exists && $model->hasTranslation($locale)) {
                    $model->deleteTranslations($locale);
                }

                continue;
            }

            $translation = $model->translateOrNew($locale);
            foreach ($fields as $field) {
                $value = $input[$field] ?? null;
                $translation->{$field} = in_array($field, $htmlFields, true)
                    ? RichText::clean($value)
                    : (is_string($value) ? trim($value) : $value);
            }
        }
    }
}
