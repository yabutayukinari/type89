<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseSlotStatus;
use App\Enums\QueueEntryStatus;
use App\Enums\SlotEventType;
use App\Enums\SlotReleaseReason;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
use App\Models\SeatInventory;
use App\Models\SlotEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

final class SlotAllocator
{
    /**
     * @return list<int>
     */
    public function releaseExpired(Performance $performance, SeatInventory $inventory): array
    {
        $expired = PurchaseSlot::query()
            ->where('performance_id', $performance->id)
            ->where('status', PurchaseSlotStatus::Held)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $releasedUserIds = [];
        foreach ($expired as $slot) {
            $this->releaseSlot($slot, $inventory, SlotReleaseReason::Ttl, QueueEntryStatus::Expired);
            $releasedUserIds[] = $slot->user_id;
        }

        return $releasedUserIds;
    }

    public function releaseSlot(
        PurchaseSlot $slot,
        SeatInventory $inventory,
        SlotReleaseReason $reason,
        QueueEntryStatus $entryStatus,
    ): void {
        $inventory->remaining_seats += 1;
        $inventory->save();
        $slot->queueEntry->update(['status' => $entryStatus]);
        $this->recordEvent($slot, $inventory, SlotEventType::Released, $reason);
        $slot->delete();
    }

    /**
     * @return list<PurchaseSlot>
     */
    public function admitWaiting(Performance $performance, SeatInventory $inventory): array
    {
        $waiting = QueueEntry::query()
            ->where('performance_id', $performance->id)
            ->where('status', QueueEntryStatus::Waiting)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $assigned = [];
        foreach ($waiting as $entry) {
            $slot = $this->assignSlot($entry, $inventory);
            if ($slot instanceof PurchaseSlot && $slot->wasRecentlyCreated) {
                $assigned[] = $slot;
            }
        }

        return $assigned;
    }

    public function assignSlot(QueueEntry $entry, SeatInventory $inventory): ?PurchaseSlot
    {
        $existing = PurchaseSlot::query()
            ->where('performance_id', $entry->performance_id)
            ->where('user_id', $entry->user_id)
            ->first();

        if ($existing instanceof PurchaseSlot) {
            $entry->update(['status' => $this->entryStatusFor($existing)]);

            return $existing;
        }

        if (! $inventory->hasRemainingSeats()) {
            return null;
        }

        $assignedAt = Carbon::now();

        try {
            $slot = PurchaseSlot::query()->create([
                'performance_id' => $entry->performance_id,
                'user_id' => $entry->user_id,
                'queue_entry_id' => $entry->id,
                'status' => PurchaseSlotStatus::Held,
                'assigned_at' => $assignedAt,
                'expires_at' => $assignedAt->copy()->addSeconds((int) config('ticket.hold_ttl_seconds')),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->existingAfterConflict($entry);
        }

        $inventory->remaining_seats -= 1;
        $inventory->save();
        $entry->update(['status' => QueueEntryStatus::Admitted]);
        $this->recordEvent($slot, $inventory, SlotEventType::Held);

        return $slot;
    }

    public function recordEvent(
        PurchaseSlot $slot,
        SeatInventory $inventory,
        SlotEventType $type,
        ?SlotReleaseReason $reason = null,
    ): void {
        SlotEvent::query()->create([
            'performance_id' => $slot->performance_id,
            'user_id' => $slot->user_id,
            'queue_entry_id' => $slot->queue_entry_id,
            'type' => $type,
            'release_reason' => $reason,
            'remaining_seats_after' => $inventory->remaining_seats,
            'occurred_at' => Carbon::now(),
        ]);
    }

    private function existingAfterConflict(QueueEntry $entry): ?PurchaseSlot
    {
        $existing = PurchaseSlot::query()
            ->where('performance_id', $entry->performance_id)
            ->where('user_id', $entry->user_id)
            ->first();
        if ($existing instanceof PurchaseSlot) {
            $entry->update(['status' => $this->entryStatusFor($existing)]);
        }

        return $existing;
    }

    private function entryStatusFor(PurchaseSlot $slot): QueueEntryStatus
    {
        return $slot->isConfirmed() ? QueueEntryStatus::Confirmed : QueueEntryStatus::Admitted;
    }
}
