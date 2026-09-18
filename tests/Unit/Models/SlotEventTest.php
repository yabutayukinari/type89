<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\SlotEventType;
use App\Enums\SlotReleaseReason;
use App\Models\QueueEntry;
use App\Models\SlotEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_relations_and_release_reason(): void
    {
        $entry = QueueEntry::factory()->admitted()->create();
        $event = SlotEvent::factory()->released()->create([
            'performance_id' => $entry->performance_id,
            'user_id' => $entry->user_id,
            'queue_entry_id' => $entry->id,
            'release_reason' => SlotReleaseReason::Ttl,
            'remaining_seats_after' => 3,
        ]);

        $this->assertTrue($event->performance->is($entry->performance));
        $this->assertTrue($event->user->is($entry->user));
        $this->assertTrue($event->queueEntry->is($entry));
        $this->assertSame(SlotEventType::Released, $event->type);
        $this->assertSame(SlotReleaseReason::Ttl, $event->release_reason);
        $this->assertTrue($entry->performance->slotEvents->contains($event));
    }
}
