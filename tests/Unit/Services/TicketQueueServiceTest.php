<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PurchaseSlotStatus;
use App\Enums\QueueEntryStatus;
use App\Enums\SlotEventType;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
use App\Models\SlotEvent;
use App\Models\User;
use App\Services\TicketQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_join_recovers_when_queue_entry_create_hits_unique_constraint(): void
    {
        $performance = Performance::factory()->withCapacity(2)->create();
        $user = User::factory()->create();

        QueueEntry::creating(static function (QueueEntry $entry) use ($performance, $user): void {
            if ($entry->performance_id !== $performance->id || $entry->user_id !== $user->id) {
                return;
            }

            if (QueueEntry::query()
                ->where('performance_id', $performance->id)
                ->where('user_id', $user->id)
                ->exists()) {
                return;
            }

            QueueEntry::withoutEvents(static function () use ($performance, $user): void {
                QueueEntry::query()->create([
                    'performance_id' => $performance->id,
                    'user_id' => $user->id,
                    'status' => QueueEntryStatus::Waiting,
                    'position' => 1,
                    'joined_at' => Carbon::now(),
                ]);
            });
        });

        $admission = app(TicketQueueService::class)->join($performance, $user);

        $this->assertSame(1, QueueEntry::query()->where('user_id', $user->id)->count());
        $this->assertNotNull($admission->queueEntry);
        $this->assertTrue($admission->queueEntry->user->is($user));
        $this->assertNotNull($admission->purchaseSlot);
    }

    public function test_join_recovers_when_purchase_slot_create_hits_unique_constraint(): void
    {
        $performance = Performance::factory()->withCapacity(2)->create();
        $user = User::factory()->create();

        PurchaseSlot::creating(static function (PurchaseSlot $slot): void {
            if (PurchaseSlot::query()
                ->where('performance_id', $slot->performance_id)
                ->where('user_id', $slot->user_id)
                ->exists()) {
                return;
            }

            PurchaseSlot::withoutEvents(static function () use ($slot): void {
                PurchaseSlot::query()->create([
                    'performance_id' => $slot->performance_id,
                    'user_id' => $slot->user_id,
                    'queue_entry_id' => $slot->queue_entry_id,
                    'assigned_at' => Carbon::now(),
                    'status' => PurchaseSlotStatus::Held,
                    'expires_at' => Carbon::now()->addMinutes(3),
                ]);
            });
        });

        $admission = app(TicketQueueService::class)->join($performance, $user);

        $this->assertSame(1, PurchaseSlot::query()->where('user_id', $user->id)->count());
        $this->assertNotNull($admission->purchaseSlot);
        $this->assertSame(QueueEntryStatus::Admitted, $admission->queueEntry?->status);
        $this->assertTrue($admission->purchaseSlot->performance->is($performance));
        $this->assertTrue($admission->purchaseSlot->user->is($user));
        $this->assertTrue($admission->purchaseSlot->queueEntry->is($admission->queueEntry));
    }

    public function test_join_admits_a_waiting_entry_that_already_has_a_slot(): void
    {
        $performance = Performance::factory()->withCapacity(2)->create();
        $user = User::factory()->create();
        $entry = QueueEntry::factory()->create([
            'performance_id' => $performance->id,
            'user_id' => $user->id,
            'status' => QueueEntryStatus::Waiting,
            'position' => 1,
        ]);
        $slot = PurchaseSlot::factory()->create([
            'performance_id' => $performance->id,
            'user_id' => $user->id,
            'queue_entry_id' => $entry->id,
        ]);

        $admission = app(TicketQueueService::class)->join($performance, $user);

        $this->assertSame(QueueEntryStatus::Admitted, $entry->fresh()?->status);
        $this->assertTrue($admission->purchaseSlot?->is($slot));
        $this->assertSame(
            2,
            $performance->seatInventory()->firstOrFail()->remaining_seats,
        );
        $this->assertSame([], $admission->newlyAssignedSlots);
    }

    public function test_expire_holds_is_noop_when_nothing_is_expired(): void
    {
        $performance = Performance::factory()->withCapacity(1)->create();
        $admission = app(TicketQueueService::class)->expireHolds($performance);

        $this->assertSame([], $admission->releasedUserIds());
        $this->assertNull($admission->seatUpdateReason());
        $this->assertNull($admission->queueEntry);
    }

    public function test_view_does_not_admit_or_decrement_inventory(): void
    {
        $performance = Performance::factory()->withCapacity(2)->create();
        $user = User::factory()->create();
        app(TicketQueueService::class)->join($performance, $user);
        $remaining = $performance->seatInventory()->firstOrFail()->remaining_seats;

        $view = app(TicketQueueService::class)->view($performance, $user);

        $this->assertNotNull($view->purchaseSlot);
        $this->assertSame($remaining, $performance->seatInventory()->firstOrFail()->remaining_seats);
    }

    public function test_organizer_inventory_summarises_holds(): void
    {
        $performance = Performance::factory()->withCapacity(2)->create();
        $user = User::factory()->create();
        app(TicketQueueService::class)->join($performance, $user);

        $inventory = app(TicketQueueService::class)->organizerInventory($performance);

        $this->assertSame(1, $inventory->heldCount);
        $this->assertSame(0, $inventory->confirmedCount);
        $this->assertSame(0, $inventory->waitingCount);
        $this->assertCount(1, $inventory->currentSlots);
        $this->assertTrue($inventory->events->isNotEmpty());
    }

    public function test_join_keeps_an_existing_confirmed_slot(): void
    {
        $performance = Performance::factory()->withCapacity(2)->create();
        $user = User::factory()->create();
        $entry = QueueEntry::factory()->confirmed()->create([
            'performance_id' => $performance->id,
            'user_id' => $user->id,
            'status' => QueueEntryStatus::Confirmed,
            'position' => 1,
        ]);
        $slot = PurchaseSlot::factory()->confirmed()->create([
            'performance_id' => $performance->id,
            'user_id' => $user->id,
            'queue_entry_id' => $entry->id,
        ]);

        $admission = app(TicketQueueService::class)->join($performance, $user);

        $this->assertTrue($admission->purchaseSlot?->is($slot));
        $this->assertSame(QueueEntryStatus::Confirmed, $entry->fresh()?->status);
        $this->assertSame(2, $performance->seatInventory()->firstOrFail()->remaining_seats);
    }

    public function test_view_ignores_release_events_without_a_reason(): void
    {
        $performance = Performance::factory()->withCapacity(1)->create();
        $user = User::factory()->create();
        $entry = QueueEntry::factory()->cancelled()->create([
            'performance_id' => $performance->id,
            'user_id' => $user->id,
        ]);
        SlotEvent::factory()->released()->create([
            'performance_id' => $performance->id,
            'user_id' => $user->id,
            'queue_entry_id' => $entry->id,
            'type' => SlotEventType::Released,
            'release_reason' => null,
        ]);

        $view = app(TicketQueueService::class)->view($performance, $user);

        $this->assertNull($view->lastRelease());
    }
}
