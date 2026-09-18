<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QueueEntryStatus;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
use App\Models\SeatInventory;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 公演の待機列に並び、残席があるあいだ FIFO で購入枠を 1 人 1 枠だけ割り当てる。
 *
 * 同一公演の在庫行を lockForUpdate して直列化するため、デモ規模では
 * 二重割り当てと取りすぎを DB 制約と合わせて防ぐ。スループット最適化は対象外。
 */
class TicketQueueService
{
    public function join(Performance $performance, User $user): QueueAdmission
    {
        /** @var QueueAdmission $admission */
        $admission = DB::transaction(function () use ($performance, $user): QueueAdmission {
            $locked = $this->lockPerformance($performance);

            if (! $locked['performance']->isSaleOpen()) {
                throw ValidationException::withMessages([
                    'queue' => ['販売は現在受付中ではありません'],
                ]);
            }

            $entry = $this->findOrCreateQueueEntry($locked['performance'], $user);
            $assigned = $this->admitWaiting($locked['performance'], $locked['inventory']);

            return $this->snapshot($locked['performance']->id, $user, $entry, $assigned);
        });

        return $admission;
    }

    public function status(Performance $performance, User $user): QueueAdmission
    {
        /** @var QueueAdmission $admission */
        $admission = DB::transaction(function () use ($performance, $user): QueueAdmission {
            $locked = $this->lockPerformance($performance);
            $entry = $this->existingEntry($locked['performance'], $user);
            $assigned = [];

            if ($entry instanceof QueueEntry) {
                $assigned = $this->admitWaiting($locked['performance'], $locked['inventory']);
            }

            return $this->snapshot($locked['performance']->id, $user, $entry, $assigned);
        });

        return $admission;
    }

    /**
     * @return array{performance: Performance, inventory: SeatInventory}
     */
    private function lockPerformance(Performance $performance): array
    {
        $lockedPerformance = Performance::query()->lockForUpdate()->findOrFail($performance->id);
        $inventory = SeatInventory::query()
            ->where('performance_id', $performance->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [
            'performance' => $lockedPerformance,
            'inventory' => $inventory,
        ];
    }

    private function existingEntry(Performance $performance, User $user): ?QueueEntry
    {
        return QueueEntry::query()
            ->where('performance_id', $performance->id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function findOrCreateQueueEntry(Performance $performance, User $user): QueueEntry
    {
        $existing = $this->existingEntry($performance, $user);
        if ($existing instanceof QueueEntry) {
            return $existing;
        }

        try {
            return QueueEntry::query()->create([
                'performance_id' => $performance->id,
                'user_id' => $user->id,
                'status' => QueueEntryStatus::Waiting,
                'position' => $this->nextPosition($performance->id),
                'joined_at' => Carbon::now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return QueueEntry::query()
                ->where('performance_id', $performance->id)
                ->where('user_id', $user->id)
                ->firstOrFail();
        }
    }

    /**
     * @return list<PurchaseSlot>
     */
    private function admitWaiting(Performance $performance, SeatInventory $inventory): array
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

    private function assignSlot(QueueEntry $entry, SeatInventory $inventory): ?PurchaseSlot
    {
        $existing = PurchaseSlot::query()
            ->where('performance_id', $entry->performance_id)
            ->where('user_id', $entry->user_id)
            ->first();

        if ($existing instanceof PurchaseSlot) {
            $entry->update(['status' => QueueEntryStatus::Admitted]);

            return $existing;
        }

        if (! $inventory->hasRemainingSeats()) {
            return null;
        }

        try {
            $slot = PurchaseSlot::query()->create([
                'performance_id' => $entry->performance_id,
                'user_id' => $entry->user_id,
                'queue_entry_id' => $entry->id,
                'assigned_at' => Carbon::now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $entry->update(['status' => QueueEntryStatus::Admitted]);

            return PurchaseSlot::query()
                ->where('performance_id', $entry->performance_id)
                ->where('user_id', $entry->user_id)
                ->first();
        }

        $inventory->remaining_seats -= 1;
        $inventory->save();
        $entry->update(['status' => QueueEntryStatus::Admitted]);

        return $slot;
    }

    private function nextPosition(int $performanceId): int
    {
        $max = QueueEntry::query()
            ->where('performance_id', $performanceId)
            ->max('position');

        return ((int) $max) + 1;
    }

    /**
     * @param  list<PurchaseSlot>  $assigned
     */
    private function snapshot(
        int $performanceId,
        User $user,
        ?QueueEntry $entry,
        array $assigned,
    ): QueueAdmission {
        if ($entry instanceof QueueEntry) {
            $entry->refresh();
        }

        $performance = $this->performanceWithInventory($performanceId);
        $slot = $this->slotFor($performance, $user);

        return (new QueueAdmission($performance, $entry, $slot, $assigned))
            ->withContext(QueueContext::for($performance, $entry, $slot));
    }

    private function slotFor(Performance $performance, User $user): ?PurchaseSlot
    {
        return PurchaseSlot::query()
            ->where('performance_id', $performance->id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function performanceWithInventory(int $performanceId): Performance
    {
        return Performance::query()
            ->with(['show', 'seatInventory'])
            ->findOrFail($performanceId);
    }
}
