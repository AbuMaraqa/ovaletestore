<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Which site languages use the custom font (an Arabic typeface belongs on the Arabic pages only). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.font_locales', ['ar']);
    }

    public function down(): void
    {
        $this->migrator->delete('general.font_locales');
    }
};
