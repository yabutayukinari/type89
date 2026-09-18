<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Performance;
use App\Models\Show;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Performance>
 */
class PerformanceFactory extends Factory
{
    /**
     * @var class-string<Performance>
     */
    protected $model = Performance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'show_id' => Show::factory(),
            'price' => $this->faker->numberBetween(3000, 12000),
            'starts_at' => Carbon::now()->addWeeks(2),
            'sale_opens_at' => Carbon::now()->subMinutes(10),
            'sale_closes_at' => Carbon::now()->addDays(7),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(static function (Performance $performance): void {
            if ($performance->seatInventory()->exists()) {
                return;
            }

            $performance->seatInventory()->create([
                'capacity' => 8,
                'remaining_seats' => 8,
            ]);
        });
    }

    public function upcoming(): static
    {
        return $this->state([
            'sale_opens_at' => Carbon::now()->addHour(),
            'sale_closes_at' => Carbon::now()->addHours(3),
        ]);
    }

    public function closed(): static
    {
        return $this->state([
            'sale_opens_at' => Carbon::now()->subHours(3),
            'sale_closes_at' => Carbon::now()->subMinute(),
        ]);
    }

    public function withCapacity(int $capacity, ?int $remainingSeats = null): static
    {
        $remaining = $remainingSeats ?? $capacity;

        return $this->afterCreating(static function (Performance $performance) use ($capacity, $remaining): void {
            $performance->seatInventory()->updateOrCreate(
                ['performance_id' => $performance->id],
                [
                    'capacity' => $capacity,
                    'remaining_seats' => $remaining,
                ],
            );
        });
    }
}
