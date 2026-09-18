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
        $this->assertTrue($entry->purchaseSlot->is($slot));
    }
}
