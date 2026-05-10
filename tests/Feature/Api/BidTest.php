<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Events\BidPlaced;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withHeader('Origin', 'http://localhost:3000');
    }

    public function testPlaceBidSucceedsAndBroadcasts(): void
    {
        Event::fake();

        $auction = Auction::factory()->create([
            'starting_price' => 1000,
            'bid_increment' => 100,
            'current_price' => 1000,
        ]);
        $bidder = User::factory()->create();

        $response = $this->actingAs($bidder)->postJson(
            "/api/auctions/{$auction->id}/bids",
            ['amount' => 1000],
        );

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'auction_id' => $auction->id,
                    'amount' => 1000,
                    'bidder' => ['id' => $bidder->id],
                ],
            ]);

        $this->assertDatabaseHas('bids', [
            'auction_id' => $auction->id,
            'user_id' => $bidder->id,
            'amount' => 1000,
        ]);

        $auction->refresh();
        $this->assertSame(1000, $auction->current_price);
        $this->assertSame($bidder->id, $auction->current_winner_user_id);

        Event::assertDispatched(BidPlaced::class);
    }

    public function testNextBidMustBeAtLeastIncrementAboveCurrentPrice(): void
    {
        $previousBidder = User::factory()->create();
        $auction = Auction::factory()->create([
            'starting_price' => 1000,
            'bid_increment' => 100,
            'current_price' => 1500,
            'current_winner_user_id' => $previousBidder->id,
        ]);
        $bidder = User::factory()->create();

        $response = $this->actingAs($bidder)->postJson(
            "/api/auctions/{$auction->id}/bids",
            ['amount' => 1500],
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function testCannotBidOnOwnAuction(): void
    {
        $seller = User::factory()->create();
        $auction = Auction::factory()->create([
            'seller_user_id' => $seller->id,
            'starting_price' => 1000,
            'current_price' => 1000,
        ]);

        $response = $this->actingAs($seller)->postJson(
            "/api/auctions/{$auction->id}/bids",
            ['amount' => 2000],
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function testCannotBidOnEndedAuction(): void
    {
        $auction = Auction::factory()->ended()->create();
        $bidder = User::factory()->create();

        $response = $this->actingAs($bidder)->postJson(
            "/api/auctions/{$auction->id}/bids",
            ['amount' => 99999],
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function testCannotBidOnPendingAuction(): void
    {
        $auction = Auction::factory()->pending()->create();
        $bidder = User::factory()->create();

        $response = $this->actingAs($bidder)->postJson(
            "/api/auctions/{$auction->id}/bids",
            ['amount' => 99999],
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function testBidRequiresAuthentication(): void
    {
        $auction = Auction::factory()->create();

        $response = $this->postJson(
            "/api/auctions/{$auction->id}/bids",
            ['amount' => 99999],
        );

        $response->assertStatus(401);
    }
}
