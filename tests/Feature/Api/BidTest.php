<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Events\BidPlaced;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Auction;
use App\Models\Bid;
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

    public function test_index_returns_bids_newest_first(): void
    {
        $auction = Auction::factory()->create();
        $first = Bid::factory()->for($auction)->create(['amount' => 1000]);
        $second = Bid::factory()->for($auction)->create(['amount' => 1200]);

        $response = $this->getJson("/api/auctions/{$auction->id}/bids");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonPath('data.0.bidder.id', $second->bidder->id);
    }

    public function test_index_is_scoped_to_the_auction(): void
    {
        $auction = Auction::factory()->create();
        Bid::factory()->for($auction)->create();
        $other = Auction::factory()->create();
        Bid::factory()->for($other)->create();

        $response = $this->getJson("/api/auctions/{$auction->id}/bids");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_place_bid_succeeds_and_broadcasts(): void
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

    public function test_next_bid_must_be_at_least_increment_above_current_price(): void
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

    public function test_cannot_bid_on_own_auction(): void
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

    public function test_cannot_bid_on_ended_auction(): void
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

    public function test_cannot_bid_on_pending_auction(): void
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

    public function test_bid_requires_authentication(): void
    {
        $auction = Auction::factory()->create();

        $response = $this->postJson(
            "/api/auctions/{$auction->id}/bids",
            ['amount' => 99999],
        );

        $response->assertStatus(401);
    }

    public function test_bid_validates_amount_required(): void
    {
        $auction = Auction::factory()->create();
        $bidder = User::factory()->create();

        $response = $this->actingAs($bidder)->postJson(
            "/api/auctions/{$auction->id}/bids",
            [],
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_bid_validates_amount_is_positive_integer(): void
    {
        $auction = Auction::factory()->create();
        $bidder = User::factory()->create();

        $response = $this->actingAs($bidder)->postJson(
            "/api/auctions/{$auction->id}/bids",
            ['amount' => 0],
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_first_bid_must_meet_starting_price(): void
    {
        $auction = Auction::factory()->create([
            'starting_price' => 1000,
            'bid_increment' => 100,
            'current_price' => 1000,
            'current_winner_user_id' => null,
        ]);
        $bidder = User::factory()->create();

        $response = $this->actingAs($bidder)->postJson(
            "/api/auctions/{$auction->id}/bids",
            ['amount' => 999],
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }
}
