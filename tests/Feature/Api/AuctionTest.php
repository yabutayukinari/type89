<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withHeader('Origin', 'http://localhost:3000');
    }

    public function testIndexReturnsAllAuctions(): void
    {
        Auction::factory()->count(3)->create();

        $response = $this->getJson('/api/auctions');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function testIndexOrdersByIdDescending(): void
    {
        $first = Auction::factory()->create();
        $second = Auction::factory()->create();
        $third = Auction::factory()->create();

        $response = $this->getJson('/api/auctions');

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$third->id, $second->id, $first->id], $ids);
    }

    public function testShowReturnsSingleAuction(): void
    {
        $auction = Auction::factory()->create();

        $response = $this->getJson("/api/auctions/{$auction->id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $auction->id,
                    'title' => $auction->title,
                    'status' => 'active',
                ],
            ]);
    }

    public function testShowReturnsPendingStatusBeforeStart(): void
    {
        $auction = Auction::factory()->pending()->create();

        $response = $this->getJson("/api/auctions/{$auction->id}");

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function testShowReturnsEndedStatusAfterEnd(): void
    {
        $auction = Auction::factory()->ended()->create();

        $response = $this->getJson("/api/auctions/{$auction->id}");

        $response->assertOk()
            ->assertJsonPath('data.status', 'ended');
    }

    public function testShowIncludesCurrentWinnerWhenPresent(): void
    {
        $winner = User::factory()->create(['name' => 'Winner Jane']);
        $auction = Auction::factory()->create([
            'current_winner_user_id' => $winner->id,
            'current_price' => 5000,
        ]);

        $response = $this->getJson("/api/auctions/{$auction->id}");

        $response->assertOk()
            ->assertJsonPath('data.current_winner.id', $winner->id)
            ->assertJsonPath('data.current_winner.name', 'Winner Jane');
    }

    public function testStoreCreatesAuctionForAuthenticatedUser(): void
    {
        $user = User::factory()->create();

        $payload = [
            'title' => 'Test auction',
            'description' => 'Test description',
            'starting_price' => 1000,
            'bid_increment' => 100,
            'starts_at' => now()->subMinute()->toIso8601String(),
            'ends_at' => now()->addHour()->toIso8601String(),
        ];

        $response = $this->actingAs($user)->postJson('/api/auctions', $payload);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'title' => 'Test auction',
                    'starting_price' => 1000,
                    'current_price' => 1000,
                    'min_next_bid' => 1000,
                    'seller' => ['id' => $user->id],
                ],
            ]);

        $this->assertDatabaseHas('auctions', [
            'seller_user_id' => $user->id,
            'title' => 'Test auction',
        ]);
    }

    public function testStoreRequiresAuthentication(): void
    {
        $response = $this->postJson('/api/auctions', []);

        $response->assertStatus(401);
    }

    public function testStoreValidatesEndAfterStart(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/auctions', [
            'title' => 'x',
            'description' => 'x',
            'starting_price' => 100,
            'bid_increment' => 10,
            'starts_at' => now()->addHour()->toIso8601String(),
            'ends_at' => now()->subHour()->toIso8601String(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ends_at']);
    }
}
