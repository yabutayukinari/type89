<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseSlotStatus: string
{
    case Held = 'held';
    case Confirmed = 'confirmed';
}
