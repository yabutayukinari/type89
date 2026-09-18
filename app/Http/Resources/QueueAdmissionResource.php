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
                'assigned_at' => $slot->assigned_at->toIso8601String(),
            ],
        ];
    }
}
