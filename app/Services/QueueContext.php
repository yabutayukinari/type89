<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QueueEntryStatus;
use App\Enums\QueueWaitReason;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;

final readonly class QueueContext
{
    public function __construct(
        public int $waitingAhead,
        public int $waitingCount,
        public int $admittedCount,
        public ?QueueWaitReason $waitReason,
    ) {}

    public static function for(
        Performance $performance,
        ?QueueEntry $entry,
        ?PurchaseSlot $slot,
    ): self {
        $waitingCount = QueueEntry::query()
            ->where('performance_id', $performance->id)
            ->where('status', QueueEntryStatus::Waiting)
            ->count();
        $admittedCount = QueueEntry::query()
            ->where('performance_id', $performance->id)
            ->where('status', QueueEntryStatus::Admitted)
            ->count();

        $waitingAhead = 0;
        if ($entry instanceof QueueEntry && $entry->status === QueueEntryStatus::Waiting) {
            $waitingAhead = QueueEntry::query()
                ->where('performance_id', $performance->id)
                ->where('status', QueueEntryStatus::Waiting)
                ->where('id', '<', $entry->id)
                ->count();
        }

        return new self(
            $waitingAhead,
            $waitingCount,
            $admittedCount,
            self::waitReason($entry, $slot, $performance, $waitingAhead),
        );
    }

    private static function waitReason(
        ?QueueEntry $entry,
        ?PurchaseSlot $slot,
        Performance $performance,
        int $waitingAhead,
    ): ?QueueWaitReason {
        if ($slot instanceof PurchaseSlot) {
            return null;
        }

        if (! $entry instanceof QueueEntry || $entry->status !== QueueEntryStatus::Waiting) {
            return null;
        }

        $inventory = $performance->seatInventory;
        $remaining = $inventory === null ? 0 : $inventory->remaining_seats;
        if ($remaining <= 0) {
            return QueueWaitReason::SoldOut;
        }

        if ($waitingAhead > 0) {
            return QueueWaitReason::OthersAhead;
        }

        return QueueWaitReason::Assigning;
    }
}
