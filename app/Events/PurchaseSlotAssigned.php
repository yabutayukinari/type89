<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PurchaseSlot;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseSlotAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PurchaseSlot $slot) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->slot->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'slot.assigned';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $slot = $this->slot->loadMissing('queueEntry');

        return [
            'purchase_slot' => [
                'id' => $slot->id,
                'performance_id' => $slot->performance_id,
                'assigned_at' => $slot->assigned_at->toIso8601String(),
            ],
            'queue_entry' => [
                'id' => $slot->queueEntry->id,
                'position' => $slot->queueEntry->position,
                'status' => $slot->queueEntry->status->value,
            ],
        ];
    }
}
