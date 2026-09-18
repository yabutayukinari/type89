<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\QueueEntryStatus;
use App\Enums\SlotEventType;
use App\Enums\SlotReleaseReason;
use App\Events\PurchaseSlotAssigned;
use App\Events\QueueAdmissionUpdated;
use App\Events\SeatsUpdated;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
use App\Models\SlotEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PerformanceQueueLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withHeader('Origin', 'http://localhost:3000');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_self_cancel_releases_inventory_and_admits_the_next_waiter_fifo(): void
    {
        Event::fake();

        $performance = Performance::factory()->withCapacity(1)->create();
        $holder = User::factory()->create();
        $waiter = User::factory()->create();

        $this->actingAs($holder)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($waiter)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.purchase_slot', null)
            ->assertJsonPath('data.wait_reason', 'sold_out');

        $cancel = $this->actingAs($holder)
            ->postJson("/api/performances/{$performance->id}/queue/cancel")
            ->assertOk();

        $cancel->assertJsonPath('data.purchase_slot', null)
            ->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Cancelled->value)
            ->assertJsonPath('data.slot_release.reason', SlotReleaseReason::SelfCancel->value)
            ->assertJsonPath('data.performance.remaining_seats', 0);

        $this->assertDatabaseMissing('purchase_slots', ['user_id' => $holder->id]);
        $this->assertDatabaseHas('purchase_slots', [
            'user_id' => $waiter->id,
            'performance_id' => $performance->id,
        ]);
        $this->assertSame(0, $performance->seatInventory()->firstOrFail()->remaining_seats);
        $this->assertDatabaseHas('slot_events', [
            'user_id' => $holder->id,
            'type' => SlotEventType::Released->value,
            'release_reason' => SlotReleaseReason::SelfCancel->value,
        ]);

        $this->actingAs($waiter)
            ->getJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Admitted->value)
            ->assertJsonPath('data.purchase_slot.status', 'held');

        Event::assertDispatched(SeatsUpdated::class, function (SeatsUpdated $event): bool {
            return $event->reason->value === 'released'
                && $event->releaseReason === SlotReleaseReason::SelfCancel
                && $event->inventory->remaining_seats === 0;
        });
        Event::assertDispatched(PurchaseSlotAssigned::class, function (PurchaseSlotAssigned $event) use ($waiter): bool {
            return $event->slot->user_id === $waiter->id;
        });
        Event::assertDispatched(QueueAdmissionUpdated::class);
    }

    public function test_cancel_without_a_hold_is_rejected(): void
    {
        $performance = Performance::factory()->withCapacity(1)->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue/cancel")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['queue']);
    }

    public function test_cancel_requires_authentication(): void
    {
        $performance = Performance::factory()->create();

        $this->postJson("/api/performances/{$performance->id}/queue/cancel")
            ->assertStatus(401);
    }

    public function test_confirmed_slot_cannot_be_cancelled_and_survives_ttl(): void
    {
        $performance = Performance::factory()->withCapacity(1)->create();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue/confirm")
            ->assertOk()
            ->assertJsonPath('data.purchase_slot.status', 'confirmed')
            ->assertJsonPath('data.purchase_slot.expires_at', null)
            ->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Confirmed->value);

        $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue/cancel")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['queue']);

        Carbon::setTestNow(Carbon::now()->addMinutes(30));

        $this->actingAs($user)
            ->getJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.purchase_slot.status', 'confirmed');

        $this->assertSame(1, PurchaseSlot::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, $performance->seatInventory()->firstOrFail()->remaining_seats);
    }

    public function test_confirm_is_idempotent(): void
    {
        $performance = Performance::factory()->withCapacity(2)->create();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $first = $this->actingAs($user)->postJson("/api/performances/{$performance->id}/queue/confirm")->assertOk();
        $second = $this->actingAs($user)->postJson("/api/performances/{$performance->id}/queue/confirm")->assertOk();

        $this->assertSame($first->json('data.purchase_slot.id'), $second->json('data.purchase_slot.id'));
        $this->assertSame(1, SlotEvent::query()->where('type', SlotEventType::Confirmed->value)->count());
    }

    public function test_confirm_without_a_hold_is_rejected(): void
    {
        $performance = Performance::factory()->withCapacity(1)->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue/confirm")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['queue']);
    }

    public function test_confirm_requires_authentication(): void
    {
        $performance = Performance::factory()->create();

        $this->postJson("/api/performances/{$performance->id}/queue/confirm")
            ->assertStatus(401);
    }

    public function test_ttl_expiry_on_status_releases_and_fifo_admits_the_waiter(): void
    {
        Event::fake();

        $performance = Performance::factory()->withCapacity(1)->create();
        $holder = User::factory()->create();
        $waiter = User::factory()->create();

        $this->actingAs($holder)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($waiter)->postJson("/api/performances/{$performance->id}/queue")->assertOk();

        Carbon::setTestNow(Carbon::now()->addSeconds((int) config('ticket.hold_ttl_seconds') + 1));

        $this->actingAs($waiter)
            ->getJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Admitted->value)
            ->assertJsonPath('data.purchase_slot.status', 'held')
            ->assertJsonPath('data.performance.remaining_seats', 0);

        $this->actingAs($holder)
            ->getJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.purchase_slot', null)
            ->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Expired->value)
            ->assertJsonPath('data.slot_release.reason', SlotReleaseReason::Ttl->value);

        $this->assertDatabaseMissing('purchase_slots', ['user_id' => $holder->id]);
        $this->assertDatabaseHas('slot_events', [
            'user_id' => $holder->id,
            'type' => SlotEventType::Released->value,
            'release_reason' => SlotReleaseReason::Ttl->value,
        ]);
        Event::assertDispatched(PurchaseSlotAssigned::class);
        Event::assertDispatched(SeatsUpdated::class, function (SeatsUpdated $event): bool {
            return $event->reason->value === 'released';
        });
    }

    public function test_ttl_expiry_without_waiters_increases_remaining_seats(): void
    {
        $performance = Performance::factory()->withCapacity(2)->create();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->assertSame(1, $performance->seatInventory()->firstOrFail()->remaining_seats);

        Carbon::setTestNow(Carbon::now()->addMinutes(5));

        $this->actingAs($user)
            ->getJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Expired->value)
            ->assertJsonPath('data.performance.remaining_seats', 2);

        $this->assertSame(0, PurchaseSlot::query()->count());
    }

    public function test_cancelled_user_can_rejoin_at_the_back_of_the_queue(): void
    {
        $performance = Performance::factory()->withCapacity(1)->create();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($second)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($first)->postJson("/api/performances/{$performance->id}/queue/cancel")->assertOk();

        $rejoin = $this->actingAs($first)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertOk();

        $rejoin->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Waiting->value)
            ->assertJsonPath('data.queue_entry.position', 3)
            ->assertJsonPath('data.purchase_slot', null)
            ->assertJsonPath('data.waiting_ahead', 0);

        $this->assertSame(1, QueueEntry::query()->where('user_id', $first->id)->count());
        $this->assertNotNull(
            PurchaseSlot::query()->where('user_id', $second->id)->first(),
        );
    }

    public function test_join_records_a_held_slot_event_and_ttl(): void
    {
        $performance = Performance::factory()->withCapacity(3)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertOk();

        $response->assertJsonPath('data.purchase_slot.status', 'held')
            ->assertJsonPath('data.hold_ttl_seconds', 180);
        $this->assertNotNull($response->json('data.purchase_slot.expires_at'));
        $this->assertDatabaseHas('slot_events', [
            'user_id' => $user->id,
            'type' => SlotEventType::Held->value,
        ]);
    }

    public function test_cancel_is_idempotent_after_self_cancel(): void
    {
        $performance = Performance::factory()->withCapacity(2)->create();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($user)->postJson("/api/performances/{$performance->id}/queue/cancel")->assertOk();
        $again = $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue/cancel")
            ->assertOk();

        $again->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Cancelled->value)
            ->assertJsonPath('data.slot_release.reason', SlotReleaseReason::SelfCancel->value);
        $this->assertSame(1, SlotEvent::query()->where('type', SlotEventType::Released->value)->count());
        $this->assertSame(2, $performance->seatInventory()->firstOrFail()->remaining_seats);
    }

    public function test_waiting_user_cannot_cancel_or_confirm(): void
    {
        $performance = Performance::factory()->withCapacity(1, 0)->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Waiting->value);

        $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue/cancel")
            ->assertStatus(422);
        $this->actingAs($user)
            ->postJson("/api/performances/{$performance->id}/queue/confirm")
            ->assertStatus(422);
    }
}
