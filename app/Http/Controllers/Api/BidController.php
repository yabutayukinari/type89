<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\BidPlaced;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlaceBidRequest;
use App\Http\Resources\BidResource;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BidController extends Controller
{
    public function store(PlaceBidRequest $request, Auction $auction): JsonResource
    {
        /** @var User $bidder */
        $bidder = Auth::guard('web')->user();

        $amount = $request->integer('amount');

        $bid = DB::transaction(static function () use ($auction, $bidder, $amount): Bid {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auction->id);

            if (! $auction->isActive()) {
                throw ValidationException::withMessages([
                    'amount' => ['オークションは現在受付中ではありません'],
                ]);
            }

            if ($auction->seller_user_id === $bidder->id) {
                throw ValidationException::withMessages([
                    'amount' => ['自分の出品物には入札できません'],
                ]);
            }

            $minNext = $auction->minNextBid();
            if ($amount < $minNext) {
                throw ValidationException::withMessages([
                    'amount' => ["最低入札額は {$minNext} です"],
                ]);
            }

            $bid = Bid::create([
                'auction_id' => $auction->id,
                'user_id' => $bidder->id,
                'amount' => $amount,
            ]);

            $auction->update([
                'current_price' => $amount,
                'current_winner_user_id' => $bidder->id,
            ]);

            return $bid;
        });

        $bid->load('bidder');
        broadcast(new BidPlaced($bid));

        return new BidResource($bid);
    }
}
