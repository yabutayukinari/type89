<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Auction;
use App\Models\User;
use App\Notifications\AuctionSettled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withHeader('Origin', 'http://localhost:3000');
    }

    public function testIndexReturnsNotificationsWithUnreadCount(): void
    {
        $user = User::factory()->create();
        $auction = Auction::factory()->create(['current_winner_user_id' => $user->id]);
        $user->notify(new AuctionSettled($auction));

        $response = $this->actingAs($user)->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.data.role', 'winner');
    }

    public function testMarkAllReadClearsUnreadCount(): void
    {
        $user = User::factory()->create();
        $auction = Auction::factory()->create(['current_winner_user_id' => $user->id]);
        $user->notify(new AuctionSettled($auction));

        $this->actingAs($user)->postJson('/api/notifications/read')->assertOk();

        $user->refresh();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function testIndexRequiresAuthentication(): void
    {
        $this->getJson('/api/notifications')->assertStatus(401);
    }
}
