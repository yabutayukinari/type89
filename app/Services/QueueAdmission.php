<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QueueWaitReason;
use App\Enums\SeatUpdateReason;
use App\Enums\SlotReleaseReason;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;

final readonly class QueueAdmission
{
    /**
     * @param  list<PurchaseSlot>  $newlyAssignedSlots
     */
    public function __construct(
        public Performance $performance,
        public ?QueueEntry $queueEntry,
        public ?PurchaseSlot $purchaseSlot,
        public array $newlyAssignedSlots = [],
        public int $waitingAhead = 0,
        public int $waitingCount = 0,
        public int $admittedCount = 0,
        public ?QueueWaitReason $waitReason = null,
        public QueueEffects $effects = new QueueEffects,
    ) {}

    public function withContext(QueueContext $context): self
    {
        return new self(
            $this->performance,
            $this->queueEntry,
            $this->purchaseSlot,
            $this->newlyAssignedSlots,
            $context->waitingAhead,
            $context->waitingCount,
            $context->admittedCount,
            $context->waitReason,
            $this->effects,
        );
    }

    public function hasInventoryBroadcast(): bool
    {
        return $this->effects->seatUpdateReason !== null || $this->newlyAssignedSlots !== [];
    }

    /**
     * @return list<int>
     */
    public function releasedUserIds(): array
    {
        return $this->effects->releasedUserIds;
    }

    public function seatUpdateReason(): ?SeatUpdateReason
    {
        return $this->effects->seatUpdateReason;
    }

    public function releaseReason(): ?SlotReleaseReason
    {
        return $this->effects->releaseReason;
    }

    public function lastRelease(): ?SlotRelease
    {
        return $this->effects->lastRelease;
    }
}
