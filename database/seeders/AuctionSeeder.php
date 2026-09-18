<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class AuctionSeeder extends Seeder
{
    private const SELLER_EMAIL = 'seller@example.com';

    private const DEMO_BIDDER_EMAIL = 'test_user@example.com';

    /**
     * Seed a first-run marketplace: active lots, an ending-soon hero, and bid history.
     */
    public function run(): void
    {
        $users = $this->ensureUsers();

        foreach ($this->catalog() as $item) {
            $this->seedAuction($users, $item);
        }
    }

    /**
     * @return array<string, User>
     */
    private function ensureUsers(): array
    {
        $names = [
            self::SELLER_EMAIL => '青木 出品',
            self::DEMO_BIDDER_EMAIL => 'テストユーザー',
            'yamada@example.com' => '山田花子',
            'sato@example.com' => '佐藤健',
            'suzuki@example.com' => '鈴木みどり',
            'takahashi@example.com' => '高橋蓮',
        ];

        $users = [];
        foreach ($names as $email => $name) {
            $users[$email] = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('test1111'),
                ],
            );
        }

        return $users;
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, mixed>  $item
     */
    private function seedAuction(array $users, array $item): void
    {
        $seller = $users[self::SELLER_EMAIL];

        $auction = Auction::firstOrCreate(
            ['title' => $item['title']],
            [
                'seller_user_id' => $seller->id,
                'description' => $item['description'],
                'image_url' => $item['image_url'],
                'starting_price' => $item['starting_price'],
                'bid_increment' => $item['bid_increment'],
                'current_price' => $item['starting_price'],
                'starts_at' => $item['starts_at'],
                'ends_at' => $item['ends_at'],
            ],
        );

        if ($auction->image_url === null) {
            $auction->update(['image_url' => $item['image_url']]);
        }

        if ($auction->bids()->exists()) {
            return;
        }

        $winnerId = null;
        $price = $auction->starting_price;

        foreach ($item['bids'] as $bid) {
            $bidder = $users[$bid['email']];
            $placedAt = Carbon::now()->subMinutes($bid['minutes_ago']);

            Bid::query()->create([
                'auction_id' => $auction->id,
                'user_id' => $bidder->id,
                'amount' => $bid['amount'],
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ]);

            $price = $bid['amount'];
            $winnerId = $bidder->id;
        }

        if ($winnerId === null) {
            return;
        }

        $auction->update([
            'current_price' => $price,
            'current_winner_user_id' => $winnerId,
        ]);
    }

    /**
     * @return list<array{
     *     title: string,
     *     description: string,
     *     image_url: string,
     *     starting_price: int,
     *     bid_increment: int,
     *     starts_at: Carbon,
     *     ends_at: Carbon,
     *     bids: list<array{email: string, amount: int, minutes_ago: int}>
     * }>
     */
    private function catalog(): array
    {
        return DemoAuctionCatalog::lots(Carbon::now());
    }
}
