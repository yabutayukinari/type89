<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Auction>
 */
class AuctionFactory extends Factory
{
    /**
     * @var class-string<Auction>
     */
    protected $model = Auction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startingPrice = $this->faker->numberBetween(100, 10000);

        return [
            'seller_user_id' => User::factory(),
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'starting_price' => $startingPrice,
            'bid_increment' => $this->faker->numberBetween(10, 500),
            'current_price' => $startingPrice,
            'current_winner_user_id' => null,
            'starts_at' => Carbon::now()->subMinutes(10),
            'ends_at' => Carbon::now()->addHours(24),
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'starts_at' => Carbon::now()->addHour(),
            'ends_at' => Carbon::now()->addHours(2),
        ]);
    }

    public function ended(): static
    {
        return $this->state([
            'starts_at' => Carbon::now()->subHours(2),
            'ends_at' => Carbon::now()->subMinute(),
        ]);
    }
}
