<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\QueueEntryStatus;
use App\Events\PurchaseSlotAssigned;
use App\Events\SeatsUpdated;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PerformanceQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withHeader('Origin', 'http://localhost:3000');
    }

    public function test_join_requires_authentication(): void
    {
        $performance = Performance::factory()->create();

        $this->postJson("/api/performances/{$performance->id}/queue")
            ->assertStatus(401);
    }

    public function test_cannot_join_before_sale_opens(): void
    {
        $performance = Performance::factory()->upcoming()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['queue']);
    }

    public function test_cannot_join_after_sale_closes(): void
    {
        $performance = Performance::factory()->closed()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['queue']);
    }

    public function test_join_assigns_a_purchase_slot_and_decrements_remaining_seats(): void
    {
        Event::fake();

        $performance = Performance::factory()->withCapacity(8)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue");

        $response->assertOk()
            ->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Admitted->value)
            ->assertJsonPath('data.queue_entry.position', 1)
            ->assertJsonPath('data.performance.remaining_seats', 7);

        $this->assertNotNull($response->json('data.purchase_slot.id'));

        $this->assertDatabaseCount('purchase_slots', 1);
        $this->assertDatabaseHas('purchase_slots', [
            'performance_id' => $performance->id,
            'user_id' => $user->id,
        ]);

        Event::assertDispatched(SeatsUpdated::class);
        Event::assertDispatched(PurchaseSlotAssigned::class);
    }

    public function test_join_is_idempotent_and_does_not_double_allocate_for_the_same_user(): void
    {
        $performance = Performance::factory()->withCapacity(5)->create();
        $user = User::factory()->create();

        $first = $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertOk();

        $second = $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertOk();

        $this->assertSame(
            $first->json('data.purchase_slot.id'),
            $second->json('data.purchase_slot.id'),
        );
        $this->assertSame(1, QueueEntry::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, PurchaseSlot::query()->where('user_id', $user->id)->count());
        $this->assertSame(4, $performance->seatInventory()->firstOrFail()->remaining_seats);
    }

    public function test_fifo_assignment_does_not_over_allocate_when_demand_exceeds_seats(): void
    {
        $performance = Performance::factory()->withCapacity(2)->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $third = User::factory()->create();

        $this->actingAs($first)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($second)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($third)->postJson("/api/performances/{$performance->id}/queue")->assertOk();

        $this->assertSame(2, PurchaseSlot::query()->where('performance_id', $performance->id)->count());
        $this->assertDatabaseHas('purchase_slots', ['user_id' => $first->id, 'performance_id' => $performance->id]);
        $this->assertDatabaseHas('purchase_slots', ['user_id' => $second->id, 'performance_id' => $performance->id]);
        $this->assertDatabaseMissing('purchase_slots', ['user_id' => $third->id, 'performance_id' => $performance->id]);

        $waiting = $this->actingAs($third)
            ->getJson("/api/performances/{$performance->id}/queue")
            ->assertOk();

        $waiting->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Waiting->value)
            ->assertJsonPath('data.queue_entry.position', 3)
            ->assertJsonPath('data.purchase_slot', null)
            ->assertJsonPath('data.performance.remaining_seats', 0);

        $duplicates = PurchaseSlot::query()
            ->where('performance_id', $performance->id)
            ->selectRaw('user_id, COUNT(*) as aggregate')
            ->groupBy('user_id')
            ->having('aggregate', '>', 1)
            ->count();
        $this->assertSame(0, $duplicates);
    }

    public function test_sold_out_join_does_not_broadcast_seat_changes(): void
    {
        Event::fake();

        $performance = Performance::factory()->withCapacity(1, 0)->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.purchase_slot', null)
            ->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Waiting->value)
            ->assertJsonPath('data.performance.remaining_seats', 0);

        Event::assertNotDispatched(SeatsUpdated::class);
        Event::assertNotDispatched(PurchaseSlotAssigned::class);
    }

    public function test_queue_status_requires_authentication(): void
    {
        $performance = Performance::factory()->create();

        $this->getJson("/api/performances/{$performance->id}/queue")
            ->assertStatus(401);
    }

    public function test_queue_status_returns_nulls_when_user_has_not_joined(): void
    {
        $performance = Performance::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.queue_entry', null)
            ->assertJsonPath('data.purchase_slot', null)
            ->assertJsonPath('data.performance.id', $performance->id);
    }
}
