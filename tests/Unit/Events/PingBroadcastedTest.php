<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\PingBroadcasted;
use Illuminate\Broadcasting\Channel;
use Tests\TestCase;

class PingBroadcastedTest extends TestCase
{
    public function test_broadcast_on_returns_public_ping_channel(): void
    {
        $channels = (new PingBroadcasted('hello', '2026-01-01T00:00:00+00:00'))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('public.ping', $channels[0]->name);
    }

    public function test_broadcast_as_returns_ping(): void
    {
        $event = new PingBroadcasted('hello', '2026-01-01T00:00:00+00:00');

        $this->assertSame('ping', $event->broadcastAs());
    }

    public function test_broadcast_with_returns_message_and_emitted_at(): void
    {
        $event = new PingBroadcasted('hello', '2026-01-01T00:00:00+00:00');

        $this->assertSame([
            'message' => 'hello',
            'emitted_at' => '2026-01-01T00:00:00+00:00',
        ], $event->broadcastWith());
    }
}
