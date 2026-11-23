<?php

namespace App\Enums;

enum PaymentMethod: string
{
    /** Storefront: cash on delivery. */
    case CashOnDelivery = 'cod';

    /** POS: added to the company's account (debt), settled by recorded payments. */
    case OnAccount = 'on_account';
}
