<?php

declare(strict_types=1);

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

    public function test_settles_ended_auction_and_notifies_seller_and_winner(): void
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

    public function test_notifies_only_seller_when_no_winner(): void
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

    public function test_does_not_settle_active_auction(): void
    {
        Notification::fake();

        $auction = Auction::factory()->create(); // 既定でアクティブ

        $this->assertSame(0, Artisan::call('auctions:close'));

        $this->assertNull($auction->refresh()->settled_at);
        Notification::assertNothingSent();
    }

    public function test_is_idempotent_across_runs(): void
    {
        Notification::fake();

        $seller = User::factory()->create();
        $auction = Auction::factory()->ended()->create([
            'seller_user_id' => $seller->id,
            'current_winner_user_id' => null,
        ]);

        // 終了コードを別々の変数に受けてからまとめて検証する。同じ式を続けて
        // assertSame(0, ...) にかけると、PHPStan が 2 回目を絞り込み済みの型と見なす
        $firstExitCode = Artisan::call('auctions:close');
        $firstSettledAt = $auction->refresh()->settled_at?->toIso8601String();

        $secondExitCode = Artisan::call('auctions:close');

        $this->assertSame(0, $firstExitCode);
        $this->assertSame(0, $secondExitCode);

        // 2 回目の実行では再確定・再通知されない
        Notification::assertSentToTimes($seller, AuctionSettled::class, 1);
        $this->assertSame($firstSettledAt, $auction->refresh()->settled_at?->toIso8601String());
    }
}
