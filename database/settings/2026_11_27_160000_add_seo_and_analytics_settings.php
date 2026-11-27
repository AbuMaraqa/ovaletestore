<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** laravel-seo fallbacks + webmaster verification + the GA4 tracking id (spatie/laravel-analytics reads that property). */
return new class extends SettingsMigration
{
    private const KEYS = ['default_description', 'twitter_username', 'google_verification', 'bing_verification', 'ga_measurement_id'];

    public function up(): void
    {
        $this->migrator->add('seo.default_description', []);
        $this->migrator->add('seo.twitter_username', null);
        $this->migrator->add('seo.google_verification', null);
        $this->migrator->add('seo.bing_verification', null);
        $this->migrator->add('seo.ga_measurement_id', null);
    }

    public function down(): void
    {
        foreach (self::KEYS as $key) {
            $this->migrator->delete("seo.{$key}");
        }
    }
};
