<?php

namespace App\Enums;

/** Where an order was placed. */
enum OrderChannel: string
{
    case Web = 'web';
    case Pos = 'pos';
}
