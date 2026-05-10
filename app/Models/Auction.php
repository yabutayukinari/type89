<?php declare(strict_types=1);

namespace App\Models;

use Database\Factories\AuctionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $seller_user_id
 * @property string $title
 * @property string $description
 * @property int $starting_price
 * @property int $bid_increment
 * @property int $current_price
 * @property int|null $current_winner_user_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 *
 * @property-read User $seller
 * @property-read User|null $currentWinner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Bid> $bids
 */
class Auction extends Model
{
    /** @use HasFactory<AuctionFactory> */
    use HasFactory;

    protected $fillable = [
        'seller_user_id',
        'title',
        'description',
        'starting_price',
        'bid_increment',
        'current_price',
        'current_winner_user_id',
        'starts_at',
        'ends_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'seller_user_id' => 'integer',
        'starting_price' => 'integer',
        'bid_increment' => 'integer',
        'current_price' => 'integer',
        'current_winner_user_id' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function currentWinner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_winner_user_id');
    }

    /**
     * @return HasMany<Bid, $this>
     */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->starts_at->isFuture()) {
                    return 'pending';
                }
                if ($this->ends_at->isPast()) {
                    return 'ended';
                }
                return 'active';
            },
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function minNextBid(): int
    {
        return $this->current_winner_user_id === null
            ? $this->starting_price
            : $this->current_price + $this->bid_increment;
    }
}
