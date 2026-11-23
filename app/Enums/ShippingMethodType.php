<?php

namespace App\Enums;

enum ShippingMethodType: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';

    /** Pickup orders need no delivery address. */
    public function requiresAddress(): bool
    {
        return $this === self::Delivery;
    }
}
