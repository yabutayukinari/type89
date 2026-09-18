<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Support\Carbon;

final class DemoAuctionCatalog
{
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
    public static function lots(Carbon $now): array
    {
        return [...self::watchAndCameraLots($now), ...self::craftAndCollectibleLots($now)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function watchAndCameraLots(Carbon $now): array
    {
        return [
            [
                'title' => 'グランドセイコー メカニカル 1967',
                'description' => "昭和42年製の機械式グランドセイコー。手巻き、銀座仕上げの文字盤が残り、歩度も安定しています。\n箱・保証書はありませんが、オーバーホール済みです。",
                'image_url' => '/images/auctions/grand-seiko.svg',
                'starting_price' => 180000,
                'bid_increment' => 5000,
                'starts_at' => $now->copy()->subHours(8),
                'ends_at' => $now->copy()->addHours(8),
                'bids' => [
                    ['email' => 'sato@example.com', 'amount' => 180000, 'minutes_ago' => 220],
                    ['email' => 'yamada@example.com', 'amount' => 195000, 'minutes_ago' => 140],
                    ['email' => 'takahashi@example.com', 'amount' => 210000, 'minutes_ago' => 55],
                    ['email' => 'test_user@example.com', 'amount' => 225000, 'minutes_ago' => 18],
                ],
            ],
            [
                'title' => 'ライカ M3 後期型 ボディ',
                'description' => 'ダブルストローク後期のM3ボディ。ファインダーは明るく、シャッターは全速OK。革ケース付き。レンズは別出品です。',
                'image_url' => '/images/auctions/leica-m3.svg',
                'starting_price' => 320000,
                'bid_increment' => 10000,
                'starts_at' => $now->copy()->subHours(4),
                'ends_at' => $now->copy()->addMinutes(45),
                'bids' => [
                    ['email' => 'sato@example.com', 'amount' => 320000, 'minutes_ago' => 180],
                    ['email' => 'test_user@example.com', 'amount' => 350000, 'minutes_ago' => 95],
                    ['email' => 'suzuki@example.com', 'amount' => 370000, 'minutes_ago' => 40],
                    ['email' => 'yamada@example.com', 'amount' => 385000, 'minutes_ago' => 6],
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function craftAndCollectibleLots(Carbon $now): array
    {
        return [
            [
                'title' => '有田焼 染付牡丹文皿 江戸後期',
                'description' => '肥前有田、染付の牡丹文中皿。呉須の発色がよく、高台内に陶工銘が残ります。日常使いできる保存状態です。',
                'image_url' => '/images/auctions/arita-plate.svg',
                'starting_price' => 45000,
                'bid_increment' => 2000,
                'starts_at' => $now->copy()->subDays(1),
                'ends_at' => $now->copy()->addDays(2),
                'bids' => [
                    ['email' => 'suzuki@example.com', 'amount' => 45000, 'minutes_ago' => 400],
                    ['email' => 'yamada@example.com', 'amount' => 52000, 'minutes_ago' => 90],
                ],
            ],
            [
                'title' => 'ファミリーコンピュータ 初期型 箱付き',
                'description' => '1983年発売のファミコン初期型。箱・説明書・RFスイッチ付き。本体は動作確認済み、コントローラのケーブルに軽いヨレあり。',
                'image_url' => '/images/auctions/famicom.svg',
                'starting_price' => 12000,
                'bid_increment' => 500,
                'starts_at' => $now->copy()->subHours(6),
                'ends_at' => $now->copy()->addDay(),
                'bids' => [
                    ['email' => 'test_user@example.com', 'amount' => 12000, 'minutes_ago' => 300],
                    ['email' => 'sato@example.com', 'amount' => 14500, 'minutes_ago' => 160],
                    ['email' => 'takahashi@example.com', 'amount' => 17000, 'minutes_ago' => 40],
                ],
            ],
            [
                'title' => '十四代 大吟醸 限定醸造 720ml',
                'description' => '山形・高木酒造の限定大吟醸。製造年月新しめ、未開栓、箱付き。冷蔵保管されていた一本です。',
                'image_url' => '/images/auctions/daiginjo.svg',
                'starting_price' => 8000,
                'bid_increment' => 500,
                'starts_at' => $now->copy()->subHours(3),
                'ends_at' => $now->copy()->addHours(6),
                'bids' => [
                    ['email' => 'yamada@example.com', 'amount' => 8000, 'minutes_ago' => 110],
                    ['email' => 'suzuki@example.com', 'amount' => 11000, 'minutes_ago' => 25],
                ],
            ],
            [
                'title' => '葛飾北斎「神奈川沖浪裏」復刻木版',
                'description' => 'アダチ版画研究所による復刻。和紙の滲みと摺りの重ねが美しく、額装済み。直射日光を避けて保管されていました。',
                'image_url' => '/images/auctions/hokusai-wave.svg',
                'starting_price' => 35000,
                'bid_increment' => 1000,
                'starts_at' => $now->copy()->subHours(12),
                'ends_at' => $now->copy()->addDays(3),
                'bids' => [
                    ['email' => 'takahashi@example.com', 'amount' => 35000, 'minutes_ago' => 500],
                    ['email' => 'sato@example.com', 'amount' => 38000, 'minutes_ago' => 200],
                    ['email' => 'yamada@example.com', 'amount' => 41000, 'minutes_ago' => 70],
                ],
            ],
            [
                'title' => '南部鉄器 鉄瓶 明治期',
                'description' => '岩手・南部の古い鉄瓶。霰文、銅蓋。サビは内側に少しありますが、水漏れはありません。開始までもう少し。',
                'image_url' => '/images/auctions/tetsubin.svg',
                'starting_price' => 28000,
                'bid_increment' => 1000,
                'starts_at' => $now->copy()->addHours(2),
                'ends_at' => $now->copy()->addHours(26),
                'bids' => [],
            ],
            [
                'title' => '銀座 ヴィンテージ万年筆 18K',
                'description' => '戦前の国産18Kペン先。黒軸に金のバンド、インク窓あり。実筆確認済み、ケース付き。先ほど終了しました。',
                'image_url' => '/images/auctions/fountain-pen.svg',
                'starting_price' => 22000,
                'bid_increment' => 1000,
                'starts_at' => $now->copy()->subHours(10),
                'ends_at' => $now->copy()->subHour(),
                'bids' => [
                    ['email' => 'suzuki@example.com', 'amount' => 22000, 'minutes_ago' => 360],
                    ['email' => 'takahashi@example.com', 'amount' => 26000, 'minutes_ago' => 200],
                    ['email' => 'yamada@example.com', 'amount' => 31000, 'minutes_ago' => 80],
                ],
            ],
        ];
    }
}
