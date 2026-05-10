<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Bid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Bid */
class BidResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'auction_id' => $this->auction_id,
            'amount' => $this->amount,
            'bidder' => [
                'id' => $this->bidder->id,
                'name' => $this->bidder->name,
            ],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
