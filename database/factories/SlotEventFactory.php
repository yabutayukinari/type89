<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SlotEventType;
use App\Models\Performance;
use App\Models\QueueEntry;
use App\Models\SlotEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SlotEvent>
 */
class SlotEventFactory extends Factory
{
    /**
     * @var class-string<SlotEvent>
     */
    protected $model = SlotEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_id' => Performance::factory(),
            'user_id' => User::factory(),
            'queue_entry_id' => QueueEntry::factory(),
            'type' => SlotEventType::Held,
            'release_reason' => null,
            'remaining_seats_after' => 7,
            'occurred_at' => Carbon::now(),
        ];
    }

    public function held(): static
    {
        return $this->state([
            'type' => SlotEventType::Held,
            'release_reason' => null,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state([
            'type' => SlotEventType::Confirmed,
            'release_reason' => null,
        ]);
    }

    public function released(): static
    {
        return $this->state([
            'type' => SlotEventType::Released,
        ]);
    }
}
