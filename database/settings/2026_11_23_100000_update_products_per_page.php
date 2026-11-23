<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Dense catalog grid shows 8 products per row on wide screens → 48 per page (6 full rows). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('store.products_per_page', fn () => 48);
    }
};
