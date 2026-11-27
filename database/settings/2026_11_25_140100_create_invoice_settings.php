<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Business details printed on invoices and shipping labels (empty fields fall back to the site settings). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('invoice.business_name', null);
        $this->migrator->add('invoice.registration_number', null);
        $this->migrator->add('invoice.address', null);
        $this->migrator->add('invoice.phone', null);
        $this->migrator->add('invoice.footer_note', []);
    }

    public function down(): void
    {
        foreach (['business_name', 'registration_number', 'address', 'phone', 'footer_note'] as $key) {
            $this->migrator->delete("invoice.{$key}");
        }
    }
};
