<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Models\User;
use Closure;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use ReflectionClass;
use Tests\TestCase;

class ChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, Closure>
     */
    private function registeredChannels(): array
    {
        /** @var Broadcaster $broadcaster */
        $broadcaster = Broadcast::driver();
        $reflection = new ReflectionClass($broadcaster);
        $property = $reflection->getProperty('channels');
        $property->setAccessible(true);

        /** @var array<string, Closure> $channels */
        $channels = $property->getValue($broadcaster);

        return $channels;
    }

    public function test_public_ping_channel_authorizes_anyone(): void
    {
        $callback = $this->registeredChannels()['public.ping'];

        $this->assertTrue($callback(null));
    }

    public function test_auction_channel_authorizes_anyone(): void
    {
        $callback = $this->registeredChannels()['auction.{auctionId}'];

        $this->assertTrue($callback(null, 42));
    }

    public function test_auction_presence_channel_returns_member_info_for_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $callback = $this->registeredChannels()['auction-presence.{auctionId}'];

        $this->assertSame(['id' => $user->id, 'name' => 'Alice'], $callback($user, 7));
    }

    public function test_user_channel_authorizes_matching_user(): void
    {
        $user = User::factory()->create();
        $callback = $this->registeredChannels()['user.{userId}'];

        $this->assertTrue($callback($user, $user->id));
    }

    public function test_user_channel_rejects_different_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $callback = $this->registeredChannels()['user.{userId}'];

        $this->assertFalse($callback($user, $other->id));
    }
}
