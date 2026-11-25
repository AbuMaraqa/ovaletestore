<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Site typeface: the bundled Tajawal, or a licensed font the owner uploads (e.g. OT Magnit Arabic). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.font_family', 'tajawal');
        $this->migrator->add('general.font_name', null);
        $this->migrator->add('general.font_files', []);
        $this->migrator->add('general.font_scope', 'all');
    }

    public function down(): void
    {
        foreach (['font_family', 'font_name', 'font_files', 'font_scope'] as $key) {
            $this->migrator->delete("general.{$key}");
        }
    }
};
