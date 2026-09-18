<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\SeatUpdateReason;
use App\Enums\SlotReleaseReason;
use App\Models\SeatInventory;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeatsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SeatInventory $inventory,
        public SeatUpdateReason $reason = SeatUpdateReason::Assigned,
        public ?SlotReleaseReason $releaseReason = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel("performance.{$this->inventory->performance_id}")];
    }

    public function broadcastAs(): string
    {
        return 'seats.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $inventory = $this->inventory->loadMissing('performance');

        return [
            'performance_id' => $inventory->performance_id,
            'capacity' => $inventory->capacity,
            'remaining_seats' => $inventory->remaining_seats,
            'inventory_updated_at' => $inventory->updated_at->toIso8601String(),
            'reason' => $this->reason->value,
            'release_reason' => $this->releaseReason?->value,
        ];
    }
}
