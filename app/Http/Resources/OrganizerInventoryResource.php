<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PurchaseSlot;
use App\Models\SlotEvent;
use App\Services\OrganizerInventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrganizerInventory */
class OrganizerInventoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $inventory = $this->performance->seatInventory;

        return [
            'performance' => (new PerformanceResource($this->performance))->resolve($request),
            'held_count' => $this->heldCount,
            'confirmed_count' => $this->confirmedCount,
            'waiting_count' => $this->waitingCount,
            'remaining_seats' => $inventory?->remaining_seats,
            'capacity' => $inventory?->capacity,
            'hold_ttl_seconds' => (int) config('ticket.hold_ttl_seconds'),
            'current_slots' => $this->currentSlots->map(static function (PurchaseSlot $slot): array {
                return [
                    'id' => $slot->id,
                    'status' => $slot->status->value,
                    'assigned_at' => $slot->assigned_at->toIso8601String(),
                    'expires_at' => $slot->expires_at?->toIso8601String(),
                    'confirmed_at' => $slot->confirmed_at?->toIso8601String(),
                    'user' => [
                        'id' => $slot->user->id,
                        'name' => $slot->user->name,
                        'email' => $slot->user->email,
                    ],
                ];
            })->values()->all(),
            'events' => $this->events->map(static function (SlotEvent $event): array {
                return [
                    'id' => $event->id,
                    'type' => $event->type->value,
                    'release_reason' => $event->release_reason?->value,
                    'remaining_seats_after' => $event->remaining_seats_after,
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                    'user' => [
                        'id' => $event->user->id,
                        'name' => $event->user->name,
                        'email' => $event->user->email,
                    ],
                ];
            })->values()->all(),
        ];
    }
}
