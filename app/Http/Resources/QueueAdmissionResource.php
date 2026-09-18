<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\QueueAdmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QueueAdmission */
class QueueAdmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entry = $this->queueEntry;
        $slot = $this->purchaseSlot;
        $release = $this->lastRelease();

        return [
            'performance' => (new PerformanceResource($this->performance))->resolve($request),
            'queue_entry' => $entry === null ? null : [
                'id' => $entry->id,
                'status' => $entry->status->value,
                'position' => $entry->position,
                'joined_at' => $entry->joined_at->toIso8601String(),
            ],
            'purchase_slot' => $slot === null ? null : [
                'id' => $slot->id,
                'status' => $slot->status->value,
                'assigned_at' => $slot->assigned_at->toIso8601String(),
                'expires_at' => $slot->expires_at?->toIso8601String(),
                'confirmed_at' => $slot->confirmed_at?->toIso8601String(),
            ],
            'slot_release' => $release === null ? null : [
                'reason' => $release->reason->value,
                'released_at' => $release->releasedAt->toIso8601String(),
            ],
            'hold_ttl_seconds' => (int) config('ticket.hold_ttl_seconds'),
            'waiting_ahead' => $this->waitingAhead,
            'waiting_count' => $this->waitingCount,
            'admitted_count' => $this->admittedCount,
            'wait_reason' => $this->waitReason?->value,
        ];
    }
}
