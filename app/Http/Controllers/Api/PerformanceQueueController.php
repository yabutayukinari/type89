<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\PurchaseSlotAssigned;
use App\Events\QueueAdmissionUpdated;
use App\Events\SeatsUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\QueueAdmissionResource;
use App\Models\Performance;
use App\Models\User;
use App\Services\QueueAdmission;
use App\Services\TicketQueueService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class PerformanceQueueController extends Controller
{
    public function __construct(private readonly TicketQueueService $ticketQueue) {}

    public function show(Performance $performance): JsonResource
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $admission = $this->ticketQueue->status($performance, $user);
        $this->broadcastNewAssignments($admission);

        return new QueueAdmissionResource($admission);
    }

    public function store(Performance $performance): JsonResource
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $admission = $this->ticketQueue->join($performance, $user);
        $this->broadcastNewAssignments($admission);
        $this->broadcastPersonalUpdate($admission);

        return new QueueAdmissionResource($admission);
    }

    private function broadcastNewAssignments(QueueAdmission $admission): void
    {
        if ($admission->newlyAssignedSlots === []) {
            return;
        }

        $inventory = $admission->performance->seatInventory;
        if ($inventory !== null) {
            broadcast(new SeatsUpdated($inventory));
        }

        foreach ($admission->newlyAssignedSlots as $slot) {
            broadcast(new PurchaseSlotAssigned($slot));
        }
    }

    private function broadcastPersonalUpdate(QueueAdmission $admission): void
    {
        if ($admission->queueEntry === null) {
            return;
        }

        broadcast(new QueueAdmissionUpdated(
            $admission->queueEntry->user_id,
            (new QueueAdmissionResource($admission))->resolve(),
        ));
    }
}
