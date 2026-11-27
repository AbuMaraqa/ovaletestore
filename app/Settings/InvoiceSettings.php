<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Printed on invoices and shipping labels. Empty values fall back to the general/contact settings. */
class InvoiceSettings extends Settings
{
    /** Legal/trade name on the invoice (defaults to the site name). */
    public ?string $business_name;

    /** e.g. a registered-business / dealer number (optional). */
    public ?string $registration_number;

    public ?string $address;

    public ?string $phone;

    // locale => text printed at the bottom of the invoice
    public array $footer_note;

    public static function group(): string
    {
        return 'invoice';
    }
}
