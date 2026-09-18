<?php

declare(strict_types=1);

namespace App\Enums;

enum SlotEventType: string
{
    case Held = 'held';
    case Confirmed = 'confirmed';
    case Released = 'released';
}
