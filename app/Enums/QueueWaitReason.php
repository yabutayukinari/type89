<?php

declare(strict_types=1);

namespace App\Enums;

enum QueueWaitReason: string
{
    case SoldOut = 'sold_out';
    case OthersAhead = 'others_ahead';
    case Assigning = 'assigning';
}
