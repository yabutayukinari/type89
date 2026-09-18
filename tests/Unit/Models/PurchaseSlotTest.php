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
        $this->assertTrue($slot->isHeld());
        $this->assertFalse($slot->isConfirmed());
        $this->assertFalse($slot->isExpiredAt());
    }

    public function test_confirmed_slot_is_not_expired(): void
    {
        $slot = PurchaseSlot::factory()->confirmed()->create();

        $this->assertTrue($slot->isConfirmed());
        $this->assertFalse($slot->isHeld());
        $this->assertFalse($slot->isExpiredAt());
    }

    public function test_held_slot_past_expires_at_is_expired(): void
    {
        $slot = PurchaseSlot::factory()->expiredHold()->create();

        $this->assertTrue($slot->isExpiredAt());
        $this->assertTrue($slot->isExpiredAt(Carbon::now()));
    }

    public function test_held_slot_without_expires_at_is_not_expired(): void
    {
        $slot = PurchaseSlot::factory()->held()->create([
            'expires_at' => null,
        ]);

        $this->assertFalse($slot->isExpiredAt());
    }
}
