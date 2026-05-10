<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAuctionRequest;
use App\Http\Resources\AuctionResource;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class AuctionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $auctions = Auction::query()
            ->with(['seller', 'currentWinner'])
            ->orderByDesc('id')
            ->get();

        return AuctionResource::collection($auctions);
    }

    public function show(Auction $auction): JsonResource
    {
        $auction->load(['seller', 'currentWinner']);

        return new AuctionResource($auction);
    }

    public function store(StoreAuctionRequest $request): JsonResource
    {
        /** @var User $seller */
        $seller = Auth::guard('web')->user();

        $auction = Auction::create([
            'seller_user_id' => $seller->id,
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString(),
            'starting_price' => $request->integer('starting_price'),
            'bid_increment' => $request->integer('bid_increment'),
            'current_price' => $request->integer('starting_price'),
            'starts_at' => $request->date('starts_at'),
            'ends_at' => $request->date('ends_at'),
        ]);

        $auction->load('seller');

        return new AuctionResource($auction);
    }
}
