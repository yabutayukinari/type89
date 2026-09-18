<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\QueueEntryStatus;
use App\Events\PurchaseSlotAssigned;
use App\Events\QueueAdmissionUpdated;
use App\Events\SeatsUpdated;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
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
        Event::assertDispatched(QueueAdmissionUpdated::class);
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
            ->assertJsonPath('data.performance.remaining_seats', 0)
            ->assertJsonPath('data.wait_reason', 'sold_out')
            ->assertJsonPath('data.admitted_count', 2)
            ->assertJsonPath('data.waiting_ahead', 0)
            ->assertJsonPath('data.waiting_count', 1);

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
            ->assertJsonPath('data.performance.remaining_seats', 0)
            ->assertJsonPath('data.wait_reason', 'sold_out');

        Event::assertNotDispatched(SeatsUpdated::class);
        Event::assertNotDispatched(PurchaseSlotAssigned::class);
        Event::assertDispatched(QueueAdmissionUpdated::class);
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
            ->assertJsonPath('data.performance.id', $performance->id)
            ->assertJsonPath('data.wait_reason', null)
            ->assertJsonPath('data.waiting_ahead', 0)
            ->assertJsonPath('data.admitted_count', 0);
    }

    public function test_repeated_joins_from_the_same_user_stay_idempotent(): void
    {
        Event::fake();

        $performance = Performance::factory()->withCapacity(3)->create();
        $user = User::factory()->create();

        $slotIds = [];
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($user)
                ->postJson("/api/performances/{$performance->id}/queue")
                ->assertOk();
            $slotIds[] = $response->json('data.purchase_slot.id');
        }

        $this->assertCount(1, array_unique($slotIds));
        $this->assertSame(1, PurchaseSlot::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, QueueEntry::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, $performance->seatInventory()->firstOrFail()->remaining_seats);
        Event::assertDispatchedTimes(SeatsUpdated::class, 1);
        Event::assertDispatchedTimes(PurchaseSlotAssigned::class, 1);
        Event::assertDispatchedTimes(QueueAdmissionUpdated::class, 5);
    }

    public function test_last_seat_goes_to_exactly_one_joiner(): void
    {
        $performance = Performance::factory()->withCapacity(1)->create();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $firstJoin = $this->actingAs($first)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertOk();
        $secondJoin = $this->actingAs($second)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertOk();

        $this->assertNotNull($firstJoin->json('data.purchase_slot.id'));
        $this->assertNull($secondJoin->json('data.purchase_slot'));
        $this->assertSame(1, PurchaseSlot::query()->where('performance_id', $performance->id)->count());
        $this->assertSame(0, $performance->seatInventory()->firstOrFail()->remaining_seats);

        $secondJoin
            ->assertJsonPath('data.wait_reason', 'sold_out')
            ->assertJsonPath('data.admitted_count', 1)
            ->assertJsonPath('data.waiting_count', 1)
            ->assertJsonPath('data.waiting_ahead', 0);
    }

    public function test_reload_status_after_admit_returns_the_same_slot_without_duplicating(): void
    {
        $performance = Performance::factory()->withCapacity(4)->create();
        $user = User::factory()->create();

        $joined = $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertOk();

        $status = $this->actingAs($user)
            ->getJson("/api/performances/{$performance->id}/queue")
            ->assertOk();
        $again = $this->actingAs($user)
            ->getJson("/api/performances/{$performance->id}/queue")
            ->assertOk();

        $this->assertSame(
            $joined->json('data.purchase_slot.id'),
            $status->json('data.purchase_slot.id'),
        );
        $this->assertSame(
            $status->json('data.purchase_slot.id'),
            $again->json('data.purchase_slot.id'),
        );
        $this->assertSame(
            $joined->json('data.performance.remaining_seats'),
            $status->json('data.performance.remaining_seats'),
        );
        $this->assertNotNull($status->json('data.performance.inventory_updated_at'));
        $this->assertSame(1, PurchaseSlot::query()->where('user_id', $user->id)->count());
        $this->assertSame(QueueEntryStatus::Admitted->value, $status->json('data.queue_entry.status'));
        $this->assertNull($status->json('data.wait_reason'));
    }

    public function test_waiting_user_status_explains_that_another_user_holds_the_slot(): void
    {
        $performance = Performance::factory()->withCapacity(1)->create();
        $holder = User::factory()->create();
        $firstWaiting = User::factory()->create();
        $secondWaiting = User::factory()->create();

        $this->actingAs($holder)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($firstWaiting)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($secondWaiting)->postJson("/api/performances/{$performance->id}/queue")->assertOk();

        $this->actingAs($secondWaiting)
            ->getJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.purchase_slot', null)
            ->assertJsonPath('data.wait_reason', 'sold_out')
            ->assertJsonPath('data.admitted_count', 1)
            ->assertJsonPath('data.waiting_ahead', 1)
            ->assertJsonPath('data.waiting_count', 2)
            ->assertJsonPath('data.performance.remaining_seats', 0);
    }

    public function test_status_admits_waiting_user_in_fifo_order_when_a_seat_is_free(): void
    {
        $performance = Performance::factory()->withCapacity(1)->create();
        $holder = User::factory()->create();
        $waiting = User::factory()->create();

        $this->actingAs($holder)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($waiting)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.purchase_slot', null);

        $performance->seatInventory()->update(['remaining_seats' => 1]);

        Event::fake();

        $this->actingAs($waiting)
            ->getJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Admitted->value)
            ->assertJsonPath('data.wait_reason', null);

        $this->assertNotNull(
            PurchaseSlot::query()
                ->where('performance_id', $performance->id)
                ->where('user_id', $waiting->id)
                ->first(),
        );
        $this->assertSame(0, $performance->seatInventory()->firstOrFail()->remaining_seats);
        Event::assertDispatched(SeatsUpdated::class);
        Event::assertDispatched(PurchaseSlotAssigned::class);
    }

    public function test_join_returns_the_slot_even_when_broadcasting_throws(): void
    {
        Event::listen(SeatsUpdated::class, static function (): never {
            throw new RuntimeException('reverb down');
        });
        Event::listen(PurchaseSlotAssigned::class, static function (): never {
            throw new RuntimeException('reverb down');
        });
        Event::listen(QueueAdmissionUpdated::class, static function (): never {
            throw new RuntimeException('reverb down');
        });

        $performance = Performance::factory()->withCapacity(2)->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Admitted->value);

        $this->assertSame(1, PurchaseSlot::query()->where('user_id', $user->id)->count());
    }
}
