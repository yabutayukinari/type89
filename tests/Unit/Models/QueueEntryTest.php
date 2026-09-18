<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_relations_connect_performance_user_and_purchase_slot(): void
    {
        $entry = QueueEntry::factory()->admitted()->create();
        $slot = PurchaseSlot::factory()->create([
            'performance_id' => $entry->performance_id,
            'user_id' => $entry->user_id,
            'queue_entry_id' => $entry->id,
        ]);

        $this->assertTrue($entry->performance->is($slot->performance));
        $this->assertTrue($entry->user->is($slot->user));
        $linkedSlot = $entry->purchaseSlot;
        $this->assertNotNull($linkedSlot);
        $this->assertTrue($linkedSlot->is($slot));
    }

    public function test_cancelled_and_expired_entries_can_rejoin(): void
    {
        $cancelled = QueueEntry::factory()->cancelled()->create();
        $expired = QueueEntry::factory()->expired()->create();
        $waiting = QueueEntry::factory()->create();

        $this->assertTrue($cancelled->canRejoin());
        $this->assertTrue($expired->canRejoin());
        $this->assertFalse($waiting->canRejoin());
        $this->assertTrue($cancelled->slotEvents()->doesntExist());
    }
}
