<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Enums\SeatUpdateReason;
use App\Enums\SlotReleaseReason;
use App\Events\SeatsUpdated;
use App\Models\Performance;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatsUpdatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_on_returns_performance_channel(): void
    {
        $performance = Performance::factory()->create();
        $inventory = $performance->seatInventory;
        $this->assertNotNull($inventory);

        $channels = (new SeatsUpdated($inventory))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame("performance.{$performance->id}", $channels[0]->name);
    }

    public function test_broadcast_as_returns_seats_updated(): void
    {
        $performance = Performance::factory()->create();
        $inventory = $performance->seatInventory;
        $this->assertNotNull($inventory);

        $this->assertSame('seats.updated', (new SeatsUpdated($inventory))->broadcastAs());
    }

    public function test_broadcast_with_returns_remaining_seats(): void
    {
        $performance = Performance::factory()->withCapacity(8, 5)->create();
        $inventory = $performance->seatInventory;
        $this->assertNotNull($inventory);

        $payload = (new SeatsUpdated($inventory))->broadcastWith();

        $this->assertSame($performance->id, $payload['performance_id']);
        $this->assertSame(8, $payload['capacity']);
        $this->assertSame(5, $payload['remaining_seats']);
        $this->assertSame($inventory->updated_at->toIso8601String(), $payload['inventory_updated_at']);
        $this->assertSame('assigned', $payload['reason']);
        $this->assertNull($payload['release_reason']);
    }

    public function test_broadcast_with_includes_release_reason(): void
    {
        $performance = Performance::factory()->withCapacity(8, 6)->create();
        $inventory = $performance->seatInventory;
        $this->assertNotNull($inventory);

        $payload = (new SeatsUpdated($inventory, SeatUpdateReason::Released, SlotReleaseReason::SelfCancel))->broadcastWith();

        $this->assertSame('released', $payload['reason']);
        $this->assertSame('self_cancel', $payload['release_reason']);
    }
}
