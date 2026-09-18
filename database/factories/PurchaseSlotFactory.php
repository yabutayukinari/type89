<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PurchaseSlot>
 */
class PurchaseSlotFactory extends Factory
{
    /**
     * @var class-string<PurchaseSlot>
     */
    protected $model = PurchaseSlot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assigned_at' => Carbon::now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(static function (PurchaseSlot $slot): void {
            if ($slot->queue_entry_id !== null) {
                return;
            }

            $attributes = [];
            if ($slot->performance_id !== null) {
                $attributes['performance_id'] = $slot->performance_id;
            }
            if ($slot->user_id !== null) {
                $attributes['user_id'] = $slot->user_id;
            }

            $entry = QueueEntry::factory()->admitted()->create($attributes);
            $slot->performance_id = $entry->performance_id;
            $slot->user_id = $entry->user_id;
            $slot->queue_entry_id = $entry->id;
        });
    }
}
