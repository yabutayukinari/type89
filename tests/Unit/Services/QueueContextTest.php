<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\QueueEntryStatus;
use App\Enums\QueueWaitReason;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
use App\Models\User;
use App\Services\QueueContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiting_user_sees_sold_out_when_no_seats_remain(): void
    {
        $performance = Performance::factory()->withCapacity(1, 0)->create();
        $holder = User::factory()->create();
        $waiting = User::factory()->create();
        $holderEntry = QueueEntry::factory()->admitted()->create([
            'performance_id' => $performance->id,
            'user_id' => $holder->id,
            'position' => 1,
        ]);
        PurchaseSlot::factory()->create([
            'performance_id' => $performance->id,
            'user_id' => $holder->id,
            'queue_entry_id' => $holderEntry->id,
        ]);
        $entry = QueueEntry::factory()->create([
            'performance_id' => $performance->id,
            'user_id' => $waiting->id,
            'status' => QueueEntryStatus::Waiting,
            'position' => 2,
        ]);

        $context = QueueContext::for($performance->load('seatInventory'), $entry, null);

        $this->assertSame(QueueWaitReason::SoldOut, $context->waitReason);
        $this->assertSame(1, $context->admittedCount);
        $this->assertSame(1, $context->waitingCount);
        $this->assertSame(0, $context->waitingAhead);
    }

    public function test_waiting_user_sees_people_ahead_when_seats_remain(): void
    {
        $performance = Performance::factory()->withCapacity(2)->create();
        $ahead = QueueEntry::factory()->create([
            'performance_id' => $performance->id,
            'status' => QueueEntryStatus::Waiting,
            'position' => 1,
        ]);
        $entry = QueueEntry::factory()->create([
            'performance_id' => $performance->id,
            'status' => QueueEntryStatus::Waiting,
            'position' => 2,
        ]);

        $context = QueueContext::for($performance->load('seatInventory'), $entry, null);

        $this->assertSame(QueueWaitReason::OthersAhead, $context->waitReason);
        $this->assertSame(1, $context->waitingAhead);
        $this->assertSame(2, $context->waitingCount);
        $this->assertTrue($ahead->id < $entry->id);
    }

    public function test_next_waiting_user_sees_assigning_when_seats_remain(): void
    {
        $performance = Performance::factory()->withCapacity(2)->create();
        $entry = QueueEntry::factory()->create([
            'performance_id' => $performance->id,
            'status' => QueueEntryStatus::Waiting,
            'position' => 1,
        ]);

        $context = QueueContext::for($performance->load('seatInventory'), $entry, null);

        $this->assertSame(QueueWaitReason::Assigning, $context->waitReason);
        $this->assertSame(0, $context->waitingAhead);
    }

    public function test_confirmed_holders_count_as_admitted(): void
    {
        $performance = Performance::factory()->withCapacity(1, 0)->create();
        $holder = User::factory()->create();
        $waiting = User::factory()->create();
        $holderEntry = QueueEntry::factory()->confirmed()->create([
            'performance_id' => $performance->id,
            'user_id' => $holder->id,
            'position' => 1,
        ]);
        PurchaseSlot::factory()->confirmed()->create([
            'performance_id' => $performance->id,
            'user_id' => $holder->id,
            'queue_entry_id' => $holderEntry->id,
        ]);
        $entry = QueueEntry::factory()->create([
            'performance_id' => $performance->id,
            'user_id' => $waiting->id,
            'status' => QueueEntryStatus::Waiting,
            'position' => 2,
        ]);

        $context = QueueContext::for($performance->load('seatInventory'), $entry, null);

        $this->assertSame(1, $context->admittedCount);
        $this->assertSame(QueueWaitReason::SoldOut, $context->waitReason);
    }
}
