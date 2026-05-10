<?php declare(strict_types=1);

namespace App\Events;

use App\Models\Bid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Bid $bid)
    {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel("auction.{$this->bid->auction_id}")];
    }

    public function broadcastAs(): string
    {
        return 'bid.placed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $bid = $this->bid->loadMissing('bidder', 'auction');

        return [
            'bid' => [
                'id' => $bid->id,
                'amount' => $bid->amount,
                'created_at' => $bid->created_at->toIso8601String(),
                'bidder' => [
                    'id' => $bid->bidder->id,
                    'name' => $bid->bidder->name,
                ],
            ],
            'auction' => [
                'id' => $bid->auction->id,
                'current_price' => $bid->auction->current_price,
                'min_next_bid' => $bid->auction->minNextBid(),
                'current_winner' => [
                    'id' => $bid->bidder->id,
                    'name' => $bid->bidder->name,
                ],
            ],
        ];
    }
}
