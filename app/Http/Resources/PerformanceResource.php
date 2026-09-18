<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Performance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Performance */
class PerformanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $inventory = $this->seatInventory;

        return [
            'id' => $this->id,
            'price' => $this->price,
            'starts_at' => $this->starts_at->toIso8601String(),
            'sale_opens_at' => $this->sale_opens_at->toIso8601String(),
            'sale_closes_at' => $this->sale_closes_at->toIso8601String(),
            'sale_status' => $this->sale_status->value,
            'capacity' => $inventory?->capacity,
            'remaining_seats' => $inventory?->remaining_seats,
            'show' => [
                'id' => $this->show->id,
                'title' => $this->show->title,
                'description' => $this->show->description,
                'venue_label' => $this->show->venue_label,
            ],
        ];
    }
}
