<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** The flat fee now lives in shipping_methods (see 2026_11_23_200000_create_shipping_methods_table). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        if ($this->migrator->exists('store.shipping_fee')) {
            $this->migrator->delete('store.shipping_fee');
        }
    }
};
