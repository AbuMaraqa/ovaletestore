<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class StoreSettings extends Settings
{
    // Currencies (and the base currency) live in App\Models\Currency (admin → Currencies).

    public int $products_per_page;

    public bool $show_out_of_stock;

    /** Minimum order subtotal in minor units (0 = none). */
    public int $min_order_total;

    // Shipping costs live in App\Models\ShippingMethod (admin → Shipping methods).

    public bool $guest_checkout;

    public static function group(): string
    {
        return 'store';
    }
}
