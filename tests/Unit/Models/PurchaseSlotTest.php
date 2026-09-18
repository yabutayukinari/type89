<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurchaseSlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_slot_for_the_same_user_and_performance_is_rejected(): void
    {
        $entry = QueueEntry::factory()->admitted()->create();
        PurchaseSlot::factory()->create([
            'performance_id' => $entry->performance_id,
            'user_id' => $entry->user_id,
            'queue_entry_id' => $entry->id,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        PurchaseSlot::query()->create([
            'performance_id' => $entry->performance_id,
            'user_id' => $entry->user_id,
            'queue_entry_id' => $entry->id,
            'assigned_at' => Carbon::now(),
        ]);
    }

    public function test_belongs_to_performance_user_and_queue_entry(): void
    {
        $entry = QueueEntry::factory()->admitted()->create();
        $slot = PurchaseSlot::factory()->create([
            'performance_id' => $entry->performance_id,
            'user_id' => $entry->user_id,
            'queue_entry_id' => $entry->id,
        ]);

        $this->assertTrue($slot->performance->is($entry->performance));
        $this->assertTrue($slot->user->is($entry->user));
        $this->assertTrue($slot->queueEntry->is($entry));
    }
}
