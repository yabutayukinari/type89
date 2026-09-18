<?php declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\SeatsUpdated;
use App\Models\Performance;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatsUpdatedTest extends TestCase
{
    use RefreshDatabase;

    public function testBroadcastOnReturnsPerformanceChannel(): void
    {
        $performance = Performance::factory()->create();
        $inventory = $performance->seatInventory;
        $this->assertNotNull($inventory);

        $channels = (new SeatsUpdated($inventory))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame("performance.{$performance->id}", $channels[0]->name);
    }

    public function testBroadcastAsReturnsSeatsUpdated(): void
    {
        $performance = Performance::factory()->create();
        $inventory = $performance->seatInventory;
        $this->assertNotNull($inventory);

        $this->assertSame('seats.updated', (new SeatsUpdated($inventory))->broadcastAs());
    }

    public function testBroadcastWithReturnsRemainingSeats(): void
    {
        $performance = Performance::factory()->withCapacity(8, 5)->create();
        $inventory = $performance->seatInventory;
        $this->assertNotNull($inventory);

        $payload = (new SeatsUpdated($inventory))->broadcastWith();

        $this->assertSame($performance->id, $payload['performance_id']);
        $this->assertSame(8, $payload['capacity']);
        $this->assertSame(5, $payload['remaining_seats']);
    }
}
