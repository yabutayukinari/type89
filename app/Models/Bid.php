<?php declare(strict_types=1);

namespace App\Models;

use Database\Factories\BidFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $auction_id
 * @property int $user_id
 * @property int $amount
 * @property Carbon $created_at
 *
 * @property-read Auction $auction
 * @property-read User $bidder
 */
class Bid extends Model
{
    /** @use HasFactory<BidFactory> */
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'user_id',
        'amount',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'auction_id' => 'integer',
        'user_id' => 'integer',
        'amount' => 'integer',
    ];

    /**
     * @return BelongsTo<Auction, $this>
     */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function bidder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
