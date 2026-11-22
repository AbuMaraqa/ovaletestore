<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class StoreSettings extends Settings
{
    public string $currency;

    public int $products_per_page;

    public bool $show_out_of_stock;

    /** Minimum order subtotal in minor units (0 = none). */
    public int $min_order_total;

    /** Flat shipping fee in minor units. */
    public int $shipping_fee;

    public bool $guest_checkout;

    public static function group(): string
    {
        return 'store';
    }
}
