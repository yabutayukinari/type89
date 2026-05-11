<?php declare(strict_types=1);

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

    public function testPublicPingChannelAuthorizesAnyone(): void
    {
        $callback = $this->registeredChannels()['public.ping'];

        $this->assertTrue($callback(null));
    }

    public function testAuctionChannelAuthorizesAnyone(): void
    {
        $callback = $this->registeredChannels()['auction.{auctionId}'];

        $this->assertTrue($callback(null, 42));
    }

    public function testUserChannelAuthorizesMatchingUser(): void
    {
        $user = User::factory()->create();
        $callback = $this->registeredChannels()['user.{userId}'];

        $this->assertTrue($callback($user, $user->id));
    }

    public function testUserChannelRejectsDifferentUser(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $callback = $this->registeredChannels()['user.{userId}'];

        $this->assertFalse($callback($user, $other->id));
    }
}
