<?php

namespace App\Console\Commands;

use App\Support\Seo\SitemapBuilder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * Rebuilds every XML sitemap (index + language × type) into the cache that /sitemap.xml serves.
 * Runs nightly (routes/console.php) and belongs in the deploy script. `--write` also saves static copies
 * (e.g. to submit or inspect offline) — they are never served from public/, the routes stay the source.
 */
#[Signature('sitemap:generate {--write= : Directory to also write the XML files to}')]
#[Description('Rebuild the cached XML sitemaps (spatie/laravel-sitemap)')]
class GenerateSitemap extends Command
{
    public function handle(SitemapBuilder $builder): int
    {
        $count = $builder->warm(fn (string $name) => $this->components->task($name));

        if ($dir = $this->option('write')) {
            File::ensureDirectoryExists($dir);
            File::put($dir.'/sitemap.xml', $builder->xml());
            foreach (array_keys(LaravelLocalization::getSupportedLocales()) as $locale) {
                foreach (SitemapBuilder::TYPES as $type) {
                    File::put("{$dir}/{$locale}-{$type}.xml", $builder->xml($locale, $type));
                }
            }
            $this->components->info("Written to {$dir}");
        }

        $this->components->info("{$count} sitemaps + index ready: ".route('sitemap.index'));

        return self::SUCCESS;
    }
}
