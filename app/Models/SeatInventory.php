<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SeatInventoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $performance_id
 * @property int $capacity
 * @property int $remaining_seats
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Performance $performance
 */
class SeatInventory extends Model
{
    /** @use HasFactory<SeatInventoryFactory> */
    use HasFactory;

    protected $fillable = [
        'performance_id',
        'capacity',
        'remaining_seats',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'performance_id' => 'integer',
        'capacity' => 'integer',
        'remaining_seats' => 'integer',
    ];

    /**
     * @return BelongsTo<Performance, $this>
     */
    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function hasRemainingSeats(): bool
    {
        return $this->remaining_seats > 0;
    }
}
