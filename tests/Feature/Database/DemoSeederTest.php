<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_working_demo_login_and_live_marketplace(): void
    {
        $this->seed(DatabaseSeeder::class);

        $demoBidder = User::query()->where('email', 'test_user@example.com')->first();
        $this->assertNotNull($demoBidder);
        $this->assertTrue(Hash::check('test1111', $demoBidder->password));

        $this->assertDatabaseHas('users', ['email' => 'seller@example.com']);

        $auctions = Auction::query()->with('bids')->get();
        $this->assertGreaterThanOrEqual(6, $auctions->count());
        $this->assertTrue($auctions->contains(
            static fn (Auction $auction): bool => $auction->title === 'ライカ M3 後期型 ボディ',
        ));
        $this->assertTrue($auctions->contains(
            static fn (Auction $auction): bool => $auction->title === 'グランドセイコー メカニカル 1967',
        ));

        $active = $auctions->filter(static fn (Auction $auction): bool => $auction->isActive());
        $this->assertGreaterThanOrEqual(5, $active->count());

        $endingSoon = $active->filter(
            static fn (Auction $auction): bool => $auction->ends_at->lessThan(Carbon::now()->addHour()),
        );
        $this->assertTrue($endingSoon->isNotEmpty(), 'expected an auction ending within an hour');

        foreach ($active as $auction) {
            $this->assertNotEmpty($auction->image_url);
        }

        $this->assertGreaterThanOrEqual(3, Auction::query()->has('bids')->count());
        $this->assertTrue(Bid::query()->exists());

        $hero = $endingSoon->first();
        $this->assertSame('ライカ M3 後期型 ボディ', $hero->title);
        $this->assertNotSame($demoBidder->id, $hero->seller_user_id);

        $this->getJson('/api/auctions')
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'ライカ M3 後期型 ボディ',
                'image_url' => '/images/auctions/leica-m3.svg',
                'status' => 'active',
            ]);
    }

    public function test_seed_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $users = User::query()->count();
        $auctions = Auction::query()->count();
        $bids = Bid::query()->count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($users, User::query()->count());
        $this->assertSame($auctions, Auction::query()->count());
        $this->assertSame($bids, Bid::query()->count());
    }
}
