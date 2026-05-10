<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Auction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Auction */
class AuctionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'seller' => [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
            ],
            'starting_price' => $this->starting_price,
            'bid_increment' => $this->bid_increment,
            'current_price' => $this->current_price,
            'min_next_bid' => $this->minNextBid(),
            'current_winner' => $this->whenLoaded('currentWinner', fn () => $this->currentWinner ? [
                'id' => $this->currentWinner->id,
                'name' => $this->currentWinner->name,
            ] : null),
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'status' => $this->status,
        ];
    }
}
