<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PurchaseSlotStatus;
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
        $assignedAt = Carbon::now();

        return [
            'status' => PurchaseSlotStatus::Held,
            'assigned_at' => $assignedAt,
            'expires_at' => $assignedAt->copy()->addSeconds((int) config('ticket.hold_ttl_seconds')),
            'confirmed_at' => null,
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

    public function held(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseSlotStatus::Held,
            'confirmed_at' => null,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseSlotStatus::Confirmed,
            'expires_at' => null,
            'confirmed_at' => Carbon::now(),
        ]);
    }

    public function expiredHold(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseSlotStatus::Held,
            'assigned_at' => Carbon::now()->subMinutes(10),
            'expires_at' => Carbon::now()->subMinute(),
            'confirmed_at' => null,
        ]);
    }
}
