<?php

namespace App\Enums;

enum CompanyPaymentMethod: string
{
    case Cash = 'cash';
    case Cheque = 'cheque';
    case Transfer = 'transfer';
    case Other = 'other';
}
