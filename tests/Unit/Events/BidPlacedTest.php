<?php declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\BidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BidPlacedTest extends TestCase
{
    use RefreshDatabase;

    public function testBroadcastOnReturnsAuctionChannelWithId(): void
    {
        $auction = Auction::factory()->create();
        $bid = Bid::factory()->create(['auction_id' => $auction->id]);

        $channels = (new BidPlaced($bid))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame("auction.{$auction->id}", $channels[0]->name);
    }

    public function testBroadcastAsReturnsBidPlaced(): void
    {
        $bid = Bid::factory()->create();

        $this->assertSame('bid.placed', (new BidPlaced($bid))->broadcastAs());
    }

    public function testBroadcastWithReturnsExpectedPayload(): void
    {
        $bidder = User::factory()->create(['name' => 'Alice']);
        $auction = Auction::factory()->create([
            'starting_price' => 1000,
            'bid_increment' => 100,
            'current_price' => 1200,
            'current_winner_user_id' => $bidder->id,
        ]);
        $bid = Bid::factory()->create([
            'auction_id' => $auction->id,
            'user_id' => $bidder->id,
            'amount' => 1200,
        ]);

        $payload = (new BidPlaced($bid))->broadcastWith();

        $this->assertSame($bid->id, $payload['bid']['id']);
        $this->assertSame(1200, $payload['bid']['amount']);
        $this->assertSame($bidder->id, $payload['bid']['bidder']['id']);
        $this->assertSame('Alice', $payload['bid']['bidder']['name']);
        $this->assertSame($auction->id, $payload['auction']['id']);
        $this->assertSame(1200, $payload['auction']['current_price']);
        $this->assertSame(1300, $payload['auction']['min_next_bid']);
        $this->assertSame($bidder->id, $payload['auction']['current_winner']['id']);
    }
}
