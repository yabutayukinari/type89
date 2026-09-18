<?php declare(strict_types=1);

namespace App\Models;

use App\Enums\SaleStatus;
use Database\Factories\PerformanceFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $show_id
 * @property int $price
 * @property Carbon $starts_at
 * @property Carbon $sale_opens_at
 * @property Carbon $sale_closes_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SaleStatus $sale_status
 *
 * @property-read Show $show
 * @property-read SeatInventory|null $seatInventory
 * @property-read \Illuminate\Database\Eloquent\Collection<int, QueueEntry> $queueEntries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PurchaseSlot> $purchaseSlots
 */
class Performance extends Model
{
    /** @use HasFactory<PerformanceFactory> */
    use HasFactory;

    protected $fillable = [
        'show_id',
        'price',
        'starts_at',
        'sale_opens_at',
        'sale_closes_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'show_id' => 'integer',
        'price' => 'integer',
        'starts_at' => 'datetime',
        'sale_opens_at' => 'datetime',
        'sale_closes_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Show, $this>
     */
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /**
     * @return HasOne<SeatInventory, $this>
     */
    public function seatInventory(): HasOne
    {
        return $this->hasOne(SeatInventory::class);
    }

    /**
     * @return HasMany<QueueEntry, $this>
     */
    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }

    /**
     * @return HasMany<PurchaseSlot, $this>
     */
    public function purchaseSlots(): HasMany
    {
        return $this->hasMany(PurchaseSlot::class);
    }

    /**
     * @return Attribute<SaleStatus, never>
     */
    protected function saleStatus(): Attribute
    {
        return Attribute::make(
            get: function (): SaleStatus {
                if ($this->sale_opens_at->isFuture()) {
                    return SaleStatus::Upcoming;
                }
                if ($this->sale_closes_at->isPast()) {
                    return SaleStatus::Closed;
                }

                return SaleStatus::Open;
            },
        );
    }

    public function isSaleOpen(): bool
    {
        return $this->sale_status === SaleStatus::Open;
    }
}
