<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuctionTest extends TestCase
{
    use RefreshDatabase;

    public function testStatusReturnsPendingWhenStartIsInFuture(): void
    {
        $auction = Auction::factory()->pending()->create();

        $this->assertSame('pending', $auction->status);
        $this->assertFalse($auction->isActive());
    }

    public function testStatusReturnsActiveDuringWindow(): void
    {
        $auction = Auction::factory()->create([
            'starts_at' => Carbon::now()->subMinute(),
            'ends_at' => Carbon::now()->addHour(),
        ]);

        $this->assertSame('active', $auction->status);
        $this->assertTrue($auction->isActive());
    }

    public function testStatusReturnsEndedWhenEndIsInPast(): void
    {
        $auction = Auction::factory()->ended()->create();

        $this->assertSame('ended', $auction->status);
        $this->assertFalse($auction->isActive());
    }

    public function testMinNextBidIsStartingPriceWhenNoWinner(): void
    {
        $auction = Auction::factory()->create([
            'starting_price' => 1000,
            'bid_increment' => 100,
            'current_price' => 1000,
            'current_winner_user_id' => null,
        ]);

        $this->assertSame(1000, $auction->minNextBid());
    }

    public function testMinNextBidAddsIncrementToCurrentPriceWhenWinnerExists(): void
    {
        $winner = User::factory()->create();
        $auction = Auction::factory()->create([
            'starting_price' => 1000,
            'bid_increment' => 100,
            'current_price' => 1500,
            'current_winner_user_id' => $winner->id,
        ]);

        $this->assertSame(1600, $auction->minNextBid());
    }
}
