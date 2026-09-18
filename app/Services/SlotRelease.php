<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SlotReleaseReason;
use Illuminate\Support\Carbon;

final readonly class SlotRelease
{
    public function __construct(
        public SlotReleaseReason $reason,
        public Carbon $releasedAt,
    ) {}
}
