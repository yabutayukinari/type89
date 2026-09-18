<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Performance;
use App\Models\SeatInventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatInventory>
 */
class SeatInventoryFactory extends Factory
{
    /**
     * @var class-string<SeatInventory>
     */
    protected $model = SeatInventory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $capacity = 8;

        return [
            'performance_id' => Performance::factory(),
            'capacity' => $capacity,
            'remaining_seats' => $capacity,
        ];
    }
}
