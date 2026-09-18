<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Events\PingBroadcasted;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastTestControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withHeader('Origin', 'http://localhost:3000');
    }

    public function test_broadcast_test_dispatches_ping_and_returns_payload(): void
    {
        Event::fake();

        $response = $this->postJson('/api/broadcast-test');

        $response->assertOk()
            ->assertJson([
                'broadcast' => 'public.ping',
                'event' => 'ping',
                'payload' => [
                    'message' => 'pong from Laravel',
                ],
            ]);

        $response->assertJsonStructure([
            'payload' => ['message', 'emitted_at'],
        ]);

        Event::assertDispatched(PingBroadcasted::class);
    }
}
