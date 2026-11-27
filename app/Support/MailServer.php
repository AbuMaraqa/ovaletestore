<?php

namespace App\Support;

use App\Settings\MailSettings;
use Throwable;

/**
 * Applies the SMTP server saved in Admin → Settings → Email on top of the .env mail config.
 * Runs when the mail manager is first resolved (AppServiceProvider), so every mailer — web requests and
 * queue workers — uses it. Disabled or incomplete settings leave the .env configuration untouched.
 */
final class MailServer
{
    public static function apply(): void
    {
        try {
            $settings = app(MailSettings::class);
        } catch (Throwable) {
            return; // settings not migrated yet
        }

        if ($settings->from_address) {
            config(['mail.from.address' => $settings->from_address]);
        }

        if (! $settings->smtp_enabled || blank($settings->smtp_host)) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $settings->smtp_host,
            'mail.mailers.smtp.port' => $settings->smtp_port,
            // "ssl" = implicit TLS (usually port 465); "tls" = STARTTLS (usually 587), negotiated automatically.
            'mail.mailers.smtp.scheme' => $settings->smtp_encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $settings->smtp_username,
            'mail.mailers.smtp.password' => $settings->smtp_password,
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.timeout' => 20,
        ]);
    }
}
