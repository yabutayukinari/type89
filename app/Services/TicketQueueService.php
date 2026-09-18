<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseSlotStatus;
use App\Enums\QueueEntryStatus;
use App\Enums\SeatUpdateReason;
use App\Enums\SlotEventType;
use App\Enums\SlotReleaseReason;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
use App\Models\SeatInventory;
use App\Models\SlotEvent;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 公演の待機列に並び、残席があるあいだ FIFO で購入枠を 1 人 1 枠だけ割り当てる。
 *
 * 仮確保は TTL または本人キャンセルで解放し、確定後は期限切れにもキャンセルにもしない。
 * 同一公演の在庫行を lockForUpdate して直列化する。スループット最適化は対象外。
 */
class TicketQueueService
{
    public function __construct(private readonly SlotAllocator $slots) {}

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

            $releasedUserIds = $this->slots->releaseExpired($locked['performance'], $locked['inventory']);
            $entry = $this->findOrCreateQueueEntry($locked['performance'], $user);
            $assigned = $this->slots->admitWaiting($locked['performance'], $locked['inventory']);

            return $this->snapshot($locked['performance']->id, $user, $entry, $assigned, $releasedUserIds);
        });

        return $admission;
    }

    public function status(Performance $performance, User $user): QueueAdmission
    {
        /** @var QueueAdmission $admission */
        $admission = DB::transaction(function () use ($performance, $user): QueueAdmission {
            $locked = $this->lockPerformance($performance);
            $releasedUserIds = $this->slots->releaseExpired($locked['performance'], $locked['inventory']);
            $entry = $this->existingEntry($locked['performance'], $user);
            $assigned = [];

            if ($entry instanceof QueueEntry || $releasedUserIds !== []) {
                $assigned = $this->slots->admitWaiting($locked['performance'], $locked['inventory']);
            }

            return $this->snapshot($locked['performance']->id, $user, $entry, $assigned, $releasedUserIds);
        });

        return $admission;
    }

    public function confirm(Performance $performance, User $user): QueueAdmission
    {
        /** @var QueueAdmission $admission */
        $admission = DB::transaction(function () use ($performance, $user): QueueAdmission {
            $locked = $this->lockPerformance($performance);
            $releasedUserIds = $this->slots->releaseExpired($locked['performance'], $locked['inventory']);
            $assigned = $releasedUserIds === []
                ? []
                : $this->slots->admitWaiting($locked['performance'], $locked['inventory']);
            $entry = $this->existingEntry($locked['performance'], $user);
            $slot = $this->slotFor($locked['performance'], $user);

            if ($slot instanceof PurchaseSlot && $slot->isConfirmed()) {
                return $this->snapshot($locked['performance']->id, $user, $entry, $assigned, $releasedUserIds);
            }

            if (! $slot instanceof PurchaseSlot || ! $slot->isHeld()) {
                throw ValidationException::withMessages([
                    'queue' => ['確定できる仮確保がありません。期限切れの場合は待機列の先頭に枠が渡りました。'],
                ]);
            }

            $slot->update([
                'status' => PurchaseSlotStatus::Confirmed,
                'expires_at' => null,
                'confirmed_at' => Carbon::now(),
            ]);
            $entry?->update(['status' => QueueEntryStatus::Confirmed]);
            $this->slots->recordEvent($slot, $locked['inventory'], SlotEventType::Confirmed);

            return $this->snapshot($locked['performance']->id, $user, $entry, $assigned, $releasedUserIds);
        });

        return $admission;
    }

    public function cancel(Performance $performance, User $user): QueueAdmission
    {
        /** @var QueueAdmission $admission */
        $admission = DB::transaction(function () use ($performance, $user): QueueAdmission {
            $locked = $this->lockPerformance($performance);
            $releasedUserIds = $this->slots->releaseExpired($locked['performance'], $locked['inventory']);
            $entry = $this->existingEntry($locked['performance'], $user);
            $slot = $this->slotFor($locked['performance'], $user);
            $releasedUserIds = $this->applySelfCancel($slot, $entry, $locked['inventory'], $user, $releasedUserIds);
            $assigned = $this->slots->admitWaiting($locked['performance'], $locked['inventory']);

            return $this->snapshot(
                $locked['performance']->id,
                $user,
                $entry,
                $assigned,
                $releasedUserIds,
                $slot instanceof PurchaseSlot && $slot->isHeld() ? SlotReleaseReason::SelfCancel : null,
            );
        });

        return $admission;
    }

    public function expireHolds(Performance $performance): QueueAdmission
    {
        /** @var QueueAdmission $admission */
        $admission = DB::transaction(function () use ($performance): QueueAdmission {
            $locked = $this->lockPerformance($performance);
            $releasedUserIds = $this->slots->releaseExpired($locked['performance'], $locked['inventory']);
            $assigned = $releasedUserIds === []
                ? []
                : $this->slots->admitWaiting($locked['performance'], $locked['inventory']);

            return $this->snapshot($locked['performance']->id, null, null, $assigned, $releasedUserIds);
        });

        return $admission;
    }

    public function view(Performance $performance, User $user): QueueAdmission
    {
        $entry = $this->existingEntry($performance, $user);

        return $this->snapshot($performance->id, $user, $entry, []);
    }

    public function organizerInventory(Performance $performance): OrganizerInventory
    {
        $performance->load(['show', 'seatInventory']);
        $slots = PurchaseSlot::query()
            ->with('user')
            ->where('performance_id', $performance->id)
            ->orderBy('assigned_at')
            ->get();
        $events = SlotEvent::query()
            ->with('user')
            ->where('performance_id', $performance->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return new OrganizerInventory(
            $performance,
            $slots->where('status', PurchaseSlotStatus::Held)->count(),
            $slots->where('status', PurchaseSlotStatus::Confirmed)->count(),
            QueueEntry::query()
                ->where('performance_id', $performance->id)
                ->where('status', QueueEntryStatus::Waiting)
                ->count(),
            $slots,
            $events,
        );
    }

    /**
     * @param  list<int>  $releasedUserIds
     * @return list<int>
     */
    private function applySelfCancel(
        ?PurchaseSlot $slot,
        ?QueueEntry $entry,
        SeatInventory $inventory,
        User $user,
        array $releasedUserIds,
    ): array {
        if ($slot instanceof PurchaseSlot && $slot->isConfirmed()) {
            throw ValidationException::withMessages([
                'queue' => ['確定済みの枠はキャンセルできません。決済・返金のないデモでは確定後の取り消しはありません。'],
            ]);
        }

        if ($slot instanceof PurchaseSlot && $slot->isHeld()) {
            $this->slots->releaseSlot($slot, $inventory, SlotReleaseReason::SelfCancel, QueueEntryStatus::Cancelled);
            $releasedUserIds[] = $user->id;

            return array_values(array_unique($releasedUserIds));
        }

        if ($entry instanceof QueueEntry && $entry->canRejoin()) {
            return $releasedUserIds;
        }

        throw ValidationException::withMessages([
            'queue' => ['取り消せる仮確保がありません'],
        ]);
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
            return $this->rejoinIfReleased($performance, $existing);
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

    private function rejoinIfReleased(Performance $performance, QueueEntry $entry): QueueEntry
    {
        if (! $entry->canRejoin()) {
            return $entry;
        }

        $entry->update([
            'status' => QueueEntryStatus::Waiting,
            'position' => $this->nextPosition($performance->id),
            'joined_at' => Carbon::now(),
        ]);

        return $entry->refresh();
    }

    private function nextPosition(int $performanceId): int
    {
        $max = QueueEntry::query()
            ->where('performance_id', $performanceId)
            ->max('position');

        return ((int) $max) + 1;
    }

    /**
     * @param  list<int>  $releasedUserIds
     * @param  list<PurchaseSlot>  $assigned
     */
    private function seatReason(array $releasedUserIds, array $assigned): ?SeatUpdateReason
    {
        if ($releasedUserIds !== []) {
            return SeatUpdateReason::Released;
        }

        if ($assigned !== []) {
            return SeatUpdateReason::Assigned;
        }

        return null;
    }

    /**
     * @param  list<PurchaseSlot>  $assigned
     * @param  list<int>  $releasedUserIds
     */
    private function snapshot(
        int $performanceId,
        ?User $user,
        ?QueueEntry $entry,
        array $assigned,
        array $releasedUserIds = [],
        ?SlotReleaseReason $forcedReason = null,
    ): QueueAdmission {
        if ($entry instanceof QueueEntry) {
            $entry->refresh();
        }

        $performance = $this->performanceWithInventory($performanceId);
        $slot = $user instanceof User ? $this->slotFor($performance, $user) : null;
        $releaseReason = $forcedReason ?? ($releasedUserIds === [] ? null : SlotReleaseReason::Ttl);

        return (new QueueAdmission(
            $performance,
            $entry,
            $slot,
            $assigned,
            effects: new QueueEffects(
                $releasedUserIds,
                $this->seatReason($releasedUserIds, $assigned),
                $releaseReason,
                $user instanceof User ? $this->lastReleaseFor($performance, $user) : null,
            ),
        ))->withContext(QueueContext::for($performance, $entry, $slot));
    }

    private function lastReleaseFor(Performance $performance, User $user): ?SlotRelease
    {
        $event = SlotEvent::query()
            ->where('performance_id', $performance->id)
            ->where('user_id', $user->id)
            ->where('type', SlotEventType::Released)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        if (! $event instanceof SlotEvent || ! $event->release_reason instanceof SlotReleaseReason) {
            return null;
        }

        return new SlotRelease($event->release_reason, $event->occurred_at);
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
