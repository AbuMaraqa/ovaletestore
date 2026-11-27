<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Mail server (SMTP) managed from Admin → Settings → Email, at the owner's request.
 * The password is stored encrypted with APP_KEY and never sent back to the browser.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mail.smtp_enabled', false);
        $this->migrator->add('mail.smtp_host', null);
        $this->migrator->add('mail.smtp_port', 587);
        $this->migrator->add('mail.smtp_encryption', 'tls');
        $this->migrator->add('mail.smtp_username', null);
        $this->migrator->addEncrypted('mail.smtp_password', null);
    }

    public function down(): void
    {
        foreach (['smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password'] as $key) {
            $this->migrator->delete("mail.{$key}");
        }
    }
};
