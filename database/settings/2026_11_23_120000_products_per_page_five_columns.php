<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** 5 products per row → 40 per page (8 full rows). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('store.products_per_page', fn () => 40);
    }
};
