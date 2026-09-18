<?php declare(strict_types=1);

namespace App\Enums;

enum QueueEntryStatus: string
{
    case Waiting = 'waiting';
    case Admitted = 'admitted';
}
