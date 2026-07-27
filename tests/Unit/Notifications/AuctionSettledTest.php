<?php declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\Auction;
use App\Models\User;
use App\Notifications\AuctionSettled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionSettledTest extends TestCase
{
    use RefreshDatabase;

    public function testBuildsWinnerPayload(): void
    {
        $winner = User::factory()->create();
        $auction = Auction::factory()->create([
            'title' => 'Camera',
            'current_winner_user_id' => $winner->id,
            'current_price' => 5000,
        ]);

        $data = (new AuctionSettled($auction))->toArray($winner);

        $this->assertSame('winner', $data['role']);
        $this->assertTrue($data['has_winner']);
        $this->assertSame(5000, $data['final_price']);
        $this->assertStringContainsString('落札しました', $data['message']);
    }

    public function testBuildsSellerPayloadWhenSold(): void
    {
        $seller = User::factory()->create();
        $winner = User::factory()->create();
        $auction = Auction::factory()->create([
            'title' => 'Camera',
            'seller_user_id' => $seller->id,
            'current_winner_user_id' => $winner->id,
        ]);

        $data = (new AuctionSettled($auction))->toArray($seller);

        $this->assertSame('seller', $data['role']);
        $this->assertTrue($data['has_winner']);
        $this->assertStringContainsString('落札されました', $data['message']);
    }

    public function testBuildsSellerPayloadWhenNoWinner(): void
    {
        $seller = User::factory()->create();
        $auction = Auction::factory()->create([
            'title' => 'Camera',
            'seller_user_id' => $seller->id,
            'current_winner_user_id' => null,
        ]);

        $data = (new AuctionSettled($auction))->toArray($seller);

        $this->assertSame('seller', $data['role']);
        $this->assertFalse($data['has_winner']);
        $this->assertStringContainsString('入札なしで終了', $data['message']);
    }
}
