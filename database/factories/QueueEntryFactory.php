<?php declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QueueEntryStatus;
use App\Models\Performance;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<QueueEntry>
 */
class QueueEntryFactory extends Factory
{
    /**
     * @var class-string<QueueEntry>
     */
    protected $model = QueueEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_id' => Performance::factory(),
            'user_id' => User::factory(),
            'status' => QueueEntryStatus::Waiting,
            'position' => $this->faker->numberBetween(1, 50),
            'joined_at' => Carbon::now(),
        ];
    }

    public function admitted(): static
    {
        return $this->state([
            'status' => QueueEntryStatus::Admitted,
        ]);
    }
}
