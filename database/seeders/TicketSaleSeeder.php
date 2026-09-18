<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Performance;
use App\Models\Show;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class TicketSaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedPurchasers();
        $this->seedOrganizer();
        $this->seedDemoPerformance();
    }

    private function seedPurchasers(): void
    {
        $purchasers = [
            ['email' => 'test_user@example.com', 'name' => 'テストユーザー'],
            ['email' => 'demo2@example.com', 'name' => 'デモ購入者2'],
            ['email' => 'demo3@example.com', 'name' => 'デモ購入者3'],
        ];

        foreach ($purchasers as $purchaser) {
            User::firstOrCreate(
                ['email' => $purchaser['email']],
                [
                    'name' => $purchaser['name'],
                    'password' => Hash::make('test1111'),
                ],
            );
        }
    }

    private function seedOrganizer(): void
    {
        Admin::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '会場スタッフ',
                'password' => Hash::make('password'),
                'role' => AdminRole::SystemAdmin,
            ],
        );
    }

    private function seedDemoPerformance(): void
    {
        $show = Show::firstOrCreate(
            ['title' => 'MIDNIGHT CIRCUIT VOL.12'],
            [
                'description' => "倉庫を使ったミッドサイズのクラブナイト。\nフラッシュ需要のチケット販売デモです。仮確保は3分で期限切れになり、確定するまで席は確定しません。確定後のキャンセルと決済はありません。1人1枠まで。",
                'venue_label' => 'CLUB BAY / 東京',
            ],
        );

        $performance = Performance::query()->firstOrCreate(
            ['show_id' => $show->id],
            [
                'price' => 6500,
                'starts_at' => Carbon::now()->addWeeks(2)->setTime(22, 0),
                'sale_opens_at' => Carbon::now()->subMinutes(5),
                'sale_closes_at' => Carbon::now()->addDays(7),
            ],
        );

        $performance->seatInventory()->firstOrCreate(
            ['performance_id' => $performance->id],
            [
                'capacity' => 8,
                'remaining_seats' => 8,
            ],
        );
    }
}
