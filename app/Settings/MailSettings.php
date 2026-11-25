<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Who order emails come from and who receives them. The mail server itself (SMTP host, user,
 * password) stays in .env — secrets never live in settings.
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
