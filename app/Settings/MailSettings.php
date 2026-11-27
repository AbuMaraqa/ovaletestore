<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Who order emails come from, who receives them, and (optionally) the SMTP server.
 *
 * The SMTP server is managed from the admin at the owner's request; its password is the one secret kept
 * in settings, and it is stored encrypted (APP_KEY) and never sent back to the browser. When SMTP is
 * disabled the .env mail configuration is used. Applied by App\Support\MailServer.
 */
class MailSettings extends Settings
{
    /** Sender name; empty = the site name. */
    public ?string $from_name;

    /** Sender address; empty = MAIL_FROM_ADDRESS from .env. */
    public ?string $from_address;

    /** Store inboxes that receive a copy of every new order (list of email addresses). */
    public array $order_recipients;

    public bool $notify_new_order;

    public bool $customer_confirmation;

    public bool $customer_status_updates;

    public bool $smtp_enabled;

    public ?string $smtp_host;

    public int $smtp_port;

    /** 'tls' (STARTTLS), 'ssl' (implicit TLS) or 'none'. */
    public string $smtp_encryption;

    public ?string $smtp_username;

    public ?string $smtp_password;

    /** @return list<string> */
    public static function encrypted(): array
    {
        return ['smtp_password'];
    }

    public static function group(): string
    {
        return 'mail';
    }

    /** @return list<string> */
    public function recipients(): array
    {
        return array_values(array_filter($this->order_recipients, fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)));
    }
}
