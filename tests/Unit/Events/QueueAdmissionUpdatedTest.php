<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\QueueAdmissionUpdated;
use App\Models\Performance;
use App\Models\User;
use App\Services\TicketQueueService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueAdmissionUpdatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcasts_personal_queue_payload_on_the_user_channel(): void
    {
        $performance = Performance::factory()->withCapacity(1)->create();
        $user = User::factory()->create();
        $admission = app(TicketQueueService::class)->join($performance, $user);
        $this->assertNotNull($admission->queueEntry);
        $this->assertNotNull($admission->purchaseSlot);

        $payload = [
            'performance' => ['id' => $performance->id],
            'purchase_slot' => ['id' => $admission->purchaseSlot->id],
            'waiting_ahead' => 0,
            'waiting_count' => 0,
            'admitted_count' => 1,
            'wait_reason' => null,
        ];
        $event = new QueueAdmissionUpdated($user->id, $payload);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame("private-user.{$user->id}", $channels[0]->name);
        $this->assertSame('queue.updated', $event->broadcastAs());
        $this->assertSame($payload, $event->broadcastWith());
    }
}
