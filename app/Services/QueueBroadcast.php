<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SeatUpdateReason;
use App\Events\PurchaseSlotAssigned;
use App\Events\QueueAdmissionUpdated;
use App\Events\SeatsUpdated;
use App\Http\Resources\QueueAdmissionResource;
use App\Models\Performance;
use App\Models\User;
use Throwable;

final class QueueBroadcast
{
    public function __construct(private readonly TicketQueueService $ticketQueue) {}

    public function dispatch(QueueAdmission $admission, bool $notifyActor = false): void
    {
        $this->broadcastInventory($admission);

        $notifiedUserIds = [];
        foreach ($admission->newlyAssignedSlots as $slot) {
            $this->safely(fn () => broadcast(new PurchaseSlotAssigned($slot)));
            $this->broadcastUser($admission->performance, $slot->user);
            $notifiedUserIds[] = $slot->user_id;
        }

        foreach ($admission->releasedUserIds() as $userId) {
            if (in_array($userId, $notifiedUserIds, true)) {
                continue;
            }
            $user = User::query()->find($userId);
            if ($user instanceof User) {
                $this->broadcastUser($admission->performance, $user);
                $notifiedUserIds[] = $userId;
            }
        }

        if ($notifyActor && $admission->queueEntry !== null && ! in_array($admission->queueEntry->user_id, $notifiedUserIds, true)) {
            $this->safely(fn () => broadcast(new QueueAdmissionUpdated(
                $admission->queueEntry->user_id,
                (new QueueAdmissionResource($admission))->resolve(),
            )));
        }
    }

    private function broadcastInventory(QueueAdmission $admission): void
    {
        if (! $admission->hasInventoryBroadcast()) {
            return;
        }

        $inventory = $admission->performance->seatInventory;
        if ($inventory === null) {
            return;
        }

        $this->safely(fn () => broadcast(new SeatsUpdated(
            $inventory,
            $admission->seatUpdateReason() ?? SeatUpdateReason::Assigned,
            $admission->releaseReason(),
        )));
    }

    private function broadcastUser(Performance $performance, User $user): void
    {
        $personal = $this->ticketQueue->view($performance, $user);
        $this->safely(fn () => broadcast(new QueueAdmissionUpdated(
            $user->id,
            (new QueueAdmissionResource($personal))->resolve(),
        )));
    }

    private function safely(callable $broadcast): void
    {
        try {
            $broadcast();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
