<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** The store currency now lives in the `currencies` table (the default row); see the currencies migration. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->deleteIfExists('store.currency');
    }

    public function down(): void
    {
        $this->migrator->add('store.currency', 'ILS');
    }
};
