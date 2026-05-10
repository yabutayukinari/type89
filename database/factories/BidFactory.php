<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bid>
 */
class BidFactory extends Factory
{
    /**
     * @var class-string<Bid>
     */
    protected $model = Bid::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auction_id' => Auction::factory(),
            'user_id' => User::factory(),
            'amount' => $this->faker->numberBetween(100, 100000),
        ];
    }
}
