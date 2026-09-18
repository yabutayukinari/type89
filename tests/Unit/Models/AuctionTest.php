<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuctionTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_returns_pending_when_start_is_in_future(): void
    {
        $auction = Auction::factory()->pending()->create();

        $this->assertSame('pending', $auction->status);
        $this->assertFalse($auction->isActive());
    }

    public function test_status_returns_active_during_window(): void
    {
        $auction = Auction::factory()->create([
            'starts_at' => Carbon::now()->subMinute(),
            'ends_at' => Carbon::now()->addHour(),
        ]);

        $this->assertSame('active', $auction->status);
        $this->assertTrue($auction->isActive());
    }

    public function test_status_returns_ended_when_end_is_in_past(): void
    {
        $auction = Auction::factory()->ended()->create();

        $this->assertSame('ended', $auction->status);
        $this->assertFalse($auction->isActive());
    }

    public function test_min_next_bid_is_starting_price_when_no_winner(): void
    {
        $auction = Auction::factory()->create([
            'starting_price' => 1000,
            'bid_increment' => 100,
            'current_price' => 1000,
            'current_winner_user_id' => null,
        ]);

        $this->assertSame(1000, $auction->minNextBid());
    }

    public function test_min_next_bid_adds_increment_to_current_price_when_winner_exists(): void
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

    public function test_is_settled_reflects_settled_at(): void
    {
        $auction = Auction::factory()->create(['settled_at' => null]);
        $this->assertFalse($auction->isSettled());

        $auction->forceFill(['settled_at' => Carbon::now()])->save();
        $this->assertTrue($auction->isSettled());
    }

    public function test_has_winner_reflects_current_winner(): void
    {
        $auction = Auction::factory()->create(['current_winner_user_id' => null]);
        $this->assertFalse($auction->hasWinner());

        $winner = User::factory()->create();
        $auction->forceFill(['current_winner_user_id' => $winner->id])->save();
        $this->assertTrue($auction->hasWinner());
    }
}
