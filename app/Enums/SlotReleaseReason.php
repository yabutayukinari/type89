<?php

declare(strict_types=1);

namespace App\Enums;

enum SlotReleaseReason: string
{
    case SelfCancel = 'self_cancel';
    case Ttl = 'ttl';
}
