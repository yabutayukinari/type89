<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\QueueEntryStatus;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
use App\Models\User;
use App\Services\SlotAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SlotAllocatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_slot_recovers_when_create_hits_unique_constraint(): void
    {
        $performance = Performance::factory()->withCapacity(2)->create();
        $inventory = $performance->seatInventory;
        $this->assertNotNull($inventory);
        $user = User::factory()->create();
        $entry = QueueEntry::factory()->create([
            'performance_id' => $performance->id,
            'user_id' => $user->id,
            'status' => QueueEntryStatus::Waiting,
        ]);

        PurchaseSlot::creating(static function (PurchaseSlot $slot) use ($entry): void {
            if (PurchaseSlot::query()
                ->where('performance_id', $slot->performance_id)
                ->where('user_id', $slot->user_id)
                ->exists()) {
                return;
            }

            PurchaseSlot::withoutEvents(static function () use ($slot, $entry): void {
                PurchaseSlot::query()->create([
                    'performance_id' => $slot->performance_id,
                    'user_id' => $slot->user_id,
                    'queue_entry_id' => $entry->id,
                    'assigned_at' => Carbon::now(),
                    'status' => $slot->status,
                    'expires_at' => $slot->expires_at,
                ]);
            });
        });

        $assigned = app(SlotAllocator::class)->assignSlot($entry, $inventory);

        $this->assertNotNull($assigned);
        $this->assertSame(1, PurchaseSlot::query()->where('user_id', $user->id)->count());
        $this->assertSame(QueueEntryStatus::Admitted, $entry->fresh()?->status);
        $this->assertSame(2, $inventory->fresh()?->remaining_seats);
    }

    public function test_assign_slot_returns_null_when_sold_out(): void
    {
        $performance = Performance::factory()->withCapacity(1, 0)->create();
        $inventory = $performance->seatInventory;
        $this->assertNotNull($inventory);
        $entry = QueueEntry::factory()->create([
            'performance_id' => $performance->id,
            'status' => QueueEntryStatus::Waiting,
        ]);

        $this->assertNull(app(SlotAllocator::class)->assignSlot($entry, $inventory));
    }
}
