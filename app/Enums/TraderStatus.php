<?php

namespace App\Enums;

/** A customer's request for trader (wholesale) prices. */
enum TraderStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
