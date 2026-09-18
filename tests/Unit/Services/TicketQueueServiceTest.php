<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\QueueEntryStatus;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
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
}
