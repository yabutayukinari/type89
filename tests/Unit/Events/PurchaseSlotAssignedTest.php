<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\PurchaseSlotAssigned;
use App\Models\PurchaseSlot;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseSlotAssignedTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_on_returns_private_user_channel(): void
    {
        $slot = PurchaseSlot::factory()->create();

        $channels = (new PurchaseSlotAssigned($slot))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame("private-user.{$slot->user_id}", $channels[0]->name);
    }

    public function test_broadcast_as_and_payload(): void
    {
        $slot = PurchaseSlot::factory()->create();
        $event = new PurchaseSlotAssigned($slot);

        $this->assertSame('slot.assigned', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($slot->id, $payload['purchase_slot']['id']);
        $this->assertSame($slot->performance_id, $payload['purchase_slot']['performance_id']);
        $this->assertSame($slot->queueEntry->id, $payload['queue_entry']['id']);
        $this->assertSame($slot->queueEntry->position, $payload['queue_entry']['position']);
        $this->assertSame($slot->queueEntry->status->value, $payload['queue_entry']['status']);
    }
}
