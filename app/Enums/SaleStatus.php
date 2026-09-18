<?php

declare(strict_types=1);

namespace App\Enums;

enum SaleStatus: string
{
    case Upcoming = 'upcoming';
    case Open = 'open';
    case Closed = 'closed';
}
