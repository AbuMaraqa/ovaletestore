<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Support\RichText;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Applies the versioned catalog translations in database/data/translations/{locale}/*.json.
 * Files are keyed by the WooCommerce source id, so they survive re-running woo:migrate.
 *
 *   products*.json   { "<source_id>": { "name": "...", "description": "<p>…</p>" } }
 *   categories.json  { "<source_id>": { "name": "..." } }
 *
 * Existing slugs are kept (stable URLs); a slug is generated only for new translation rows.
 */
#[Signature('catalog:import-translations
    {--locale=* : Only these locales (default: every folder)}
    {--path= : Translations folder (default: database/data/translations)}')]
#[Description('Import the curated en/he catalog translations (products, categories) — idempotent')]
class CatalogImportTranslations extends Command
{
    public function handle(): int
    {
        // Bulk import: thousands of rows would bury the human audit trail.
        activity()->disableLogging();

        $base = $this->option('path') ?: database_path('data/translations');
        $locales = $this->option('locale') ?: array_map('basename', File::directories($base));
        $supported = array_keys((array) config('laravellocalization.supportedLocales'));

        $rows = [];
        foreach ($locales as $locale) {
            if (! in_array($locale, $supported, true) || $locale === 'ar') {
                $this->components->warn("Skipping unsupported locale [{$locale}].");

                continue;
            }

            $products = $this->apply($locale, Product::class, $this->load("{$base}/{$locale}", 'products'));
            $categories = $this->apply($locale, Category::class, $this->load("{$base}/{$locale}", 'categories'));
            $rows[] = [$locale, $products['updated'], $products['missing'], $categories['updated'], $categories['missing']];
        }

        $this->table(['Locale', 'Products updated', 'Products not found', 'Categories updated', 'Categories not found'], $rows);

        return self::SUCCESS;
    }

    /** @return array<string, array{name?: string, description?: ?string, short_description?: ?string}> */
    private function load(string $dir, string $prefix): array
    {
        $entries = [];
        foreach (File::glob("{$dir}/{$prefix}*.json") as $file) {
            // Names and descriptions may live in separate files: merge per id.
            $entries = array_replace_recursive($entries, json_decode((string) File::get($file), true, flags: JSON_THROW_ON_ERROR));
        }

        return $entries;
    }

    /**
     * @param  class-string<Product|Category>  $modelClass
     * @param  array<string, array<string, mixed>>  $entries
     * @return array{updated:int, missing:int}
     */
    private function apply(string $locale, string $modelClass, array $entries): array
    {
        $models = $modelClass::query()->whereIn('source_id', array_keys($entries))
            ->with('translations')->get()->keyBy('source_id');
        $updated = 0;

        foreach ($entries as $sourceId => $fields) {
            /** @var (Product|Category)|null $model */
            $model = $models->get((int) $sourceId);
            $name = trim((string) ($fields['name'] ?? ''));
            if ($model === null || $name === '') {
                continue;
            }

            DB::transaction(function () use ($model, $locale, $fields, $name): void {
                $translation = $model->translateOrNew($locale);
                $translation->setAttribute('name', $name);
                if (! $translation->exists) {
                    $translation->setAttribute('slug', null); // generated from the name
                }

                foreach (['description', 'short_description'] as $field) {
                    if (array_key_exists($field, $fields) && $this->hasColumn($model, $field)) {
                        $translation->setAttribute($field, RichText::clean($fields[$field]));
                    }
                }

                $model->save();
            });
            $updated++;
        }

        return ['updated' => $updated, 'missing' => count($entries) - $models->count()];
    }

    private function hasColumn(Model $model, string $field): bool
    {
        return $model instanceof Product || $field === 'description';
    }
}
