<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QueueEntryStatus;
use Database\Factories\QueueEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $performance_id
 * @property int $user_id
 * @property QueueEntryStatus $status
 * @property int $position
 * @property Carbon $joined_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Performance $performance
 * @property-read User $user
 * @property-read PurchaseSlot|null $purchaseSlot
 */
class QueueEntry extends Model
{
    /** @use HasFactory<QueueEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'performance_id',
        'user_id',
        'status',
        'position',
        'joined_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'performance_id' => 'integer',
        'user_id' => 'integer',
        'status' => QueueEntryStatus::class,
        'position' => 'integer',
        'joined_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Performance, $this>
     */
    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<PurchaseSlot, $this>
     */
    public function purchaseSlot(): HasOne
    {
        return $this->hasOne(PurchaseSlot::class);
    }
}
