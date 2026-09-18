<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PurchaseSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $performance_id
 * @property int $user_id
 * @property int $queue_entry_id
 * @property Carbon $assigned_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Performance $performance
 * @property-read User $user
 * @property-read QueueEntry $queueEntry
 */
class PurchaseSlot extends Model
{
    /** @use HasFactory<PurchaseSlotFactory> */
    use HasFactory;

    protected $fillable = [
        'performance_id',
        'user_id',
        'queue_entry_id',
        'assigned_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'performance_id' => 'integer',
        'user_id' => 'integer',
        'queue_entry_id' => 'integer',
        'assigned_at' => 'datetime',
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
     * @return BelongsTo<QueueEntry, $this>
     */
    public function queueEntry(): BelongsTo
    {
        return $this->belongsTo(QueueEntry::class);
    }
}
