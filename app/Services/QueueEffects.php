<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SeatUpdateReason;
use App\Enums\SlotReleaseReason;

final readonly class QueueEffects
{
    /**
     * @param  list<int>  $releasedUserIds
     */
    public function __construct(
        public array $releasedUserIds = [],
        public ?SeatUpdateReason $seatUpdateReason = null,
        public ?SlotReleaseReason $releaseReason = null,
        public ?SlotRelease $lastRelease = null,
    ) {}
}
