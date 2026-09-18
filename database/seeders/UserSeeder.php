<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Documented first-run login (a bidder, not the demo seller).
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'test_user@example.com'],
            [
                'name' => 'テストユーザー',
                'password' => Hash::make('test1111'),
            ],
        );
    }
}
