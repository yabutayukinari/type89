<?php declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Auction;
use App\Models\User;
use App\Notifications\AuctionSettled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CloseEndedAuctionsTest extends TestCase
{
    use RefreshDatabase;

    public function testSettlesEndedAuctionAndNotifiesSellerAndWinner(): void
    {
        Notification::fake();

        $seller = User::factory()->create();
        $winner = User::factory()->create();
        $auction = Auction::factory()->ended()->create([
            'seller_user_id' => $seller->id,
            'current_winner_user_id' => $winner->id,
            'current_price' => 5000,
        ]);

        $this->assertSame(0, Artisan::call('auctions:close'));

        $auction->refresh();
        $this->assertNotNull($auction->settled_at);

        Notification::assertSentTo($seller, AuctionSettled::class);
        Notification::assertSentTo($winner, AuctionSettled::class);
    }

    public function testNotifiesOnlySellerWhenNoWinner(): void
    {
        Notification::fake();

        $seller = User::factory()->create();
        Auction::factory()->ended()->create([
            'seller_user_id' => $seller->id,
            'current_winner_user_id' => null,
        ]);

        $this->assertSame(0, Artisan::call('auctions:close'));

        Notification::assertSentTo($seller, AuctionSettled::class);
        Notification::assertCount(1);
    }

    public function testDoesNotSettleActiveAuction(): void
    {
        Notification::fake();

        $auction = Auction::factory()->create(); // 既定でアクティブ

        $this->assertSame(0, Artisan::call('auctions:close'));

        $this->assertNull($auction->refresh()->settled_at);
        Notification::assertNothingSent();
    }

    public function testIsIdempotentAcrossRuns(): void
    {
        Notification::fake();

        $seller = User::factory()->create();
        $auction = Auction::factory()->ended()->create([
            'seller_user_id' => $seller->id,
            'current_winner_user_id' => null,
        ]);

        $this->assertSame(0, Artisan::call('auctions:close'));
        $firstSettledAt = $auction->refresh()->settled_at?->toIso8601String();

        $this->assertSame(0, Artisan::call('auctions:close'));

        // 2 回目の実行では再確定・再通知されない
        Notification::assertSentToTimes($seller, AuctionSettled::class, 1);
        $this->assertSame($firstSettledAt, $auction->refresh()->settled_at?->toIso8601String());
    }
}
