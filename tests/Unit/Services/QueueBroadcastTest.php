<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\SeatUpdateReason;
use App\Events\SeatsUpdated;
use App\Models\Performance;
use App\Models\User;
use App\Services\QueueAdmission;
use App\Services\QueueBroadcast;
use App\Services\QueueEffects;
use App\Services\TicketQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QueueBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_skips_inventory_when_performance_has_no_seats_row(): void
    {
        Event::fake();

        $performance = Performance::factory()->create();
        $performance->seatInventory()->delete();
        $performance->unsetRelation('seatInventory');

        $admission = new QueueAdmission(
            $performance,
            null,
            null,
            effects: new QueueEffects(seatUpdateReason: SeatUpdateReason::Assigned),
        );

        app(QueueBroadcast::class)->dispatch($admission);

        Event::assertNotDispatched(SeatsUpdated::class);
    }

    public function test_dispatch_skips_inventory_when_nothing_changed(): void
    {
        Event::fake();

        $performance = Performance::factory()->create();
        $admission = new QueueAdmission($performance, null, null);

        app(QueueBroadcast::class)->dispatch($admission);

        Event::assertNotDispatched(SeatsUpdated::class);
    }

    public function test_dispatch_notifies_actor_when_requested(): void
    {
        Event::fake();

        $performance = Performance::factory()->withCapacity(1)->create();
        $user = User::factory()->create();
        $admission = app(TicketQueueService::class)->join($performance, $user);

        app(QueueBroadcast::class)->dispatch($admission, notifyActor: true);

        Event::assertDispatched(SeatsUpdated::class, function (SeatsUpdated $event): bool {
            return $event->reason === SeatUpdateReason::Assigned;
        });
    }

    public function test_has_inventory_broadcast_follows_seat_reason(): void
    {
        $performance = Performance::factory()->create();
        $empty = new QueueAdmission($performance, null, null);
        $released = new QueueAdmission(
            $performance,
            null,
            null,
            effects: new QueueEffects(seatUpdateReason: SeatUpdateReason::Released),
        );

        $this->assertFalse($empty->hasInventoryBroadcast());
        $this->assertTrue($released->hasInventoryBroadcast());
        $this->assertSame([], $empty->releasedUserIds());
        $this->assertNull($empty->releaseReason());
        $this->assertNull($empty->lastRelease());
    }

    public function test_dispatch_does_not_double_notify_a_user_in_assigned_and_released(): void
    {
        Event::fake();

        $performance = Performance::factory()->withCapacity(1)->create();
        $user = User::factory()->create();
        $joined = app(TicketQueueService::class)->join($performance, $user);
        $this->assertNotNull($joined->purchaseSlot);

        $admission = new QueueAdmission(
            $performance,
            $joined->queueEntry,
            $joined->purchaseSlot,
            [$joined->purchaseSlot],
            effects: new QueueEffects(
                releasedUserIds: [$user->id, 0],
                seatUpdateReason: SeatUpdateReason::Released,
            ),
        );

        app(QueueBroadcast::class)->dispatch($admission);

        Event::assertDispatched(SeatsUpdated::class);
    }
}
