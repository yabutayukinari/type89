<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Auction;
use App\Notifications\AuctionSettled;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 終了時刻を過ぎた未確定オークションを落札確定し、関係者へ通知する。
 *
 * scheduler から毎分呼ばれる想定 (routes/console.php)。
 * settled_at を確定フラグとして使い、二重確定を冪等に防ぐ。
 */
class CloseEndedAuctions extends Command
{
    protected $signature = 'auctions:close';

    protected $description = '終了時刻を過ぎたオークションを落札確定し、出品者・落札者へ通知する';

    public function handle(): int
    {
        $targets = Auction::query()
            ->whereNull('settled_at')
            ->where('ends_at', '<=', now())
            ->pluck('id');

        $settled = 0;

        foreach ($targets as $auctionId) {
            $auction = $this->settle((int) $auctionId);

            if ($auction !== null) {
                $this->notify($auction);
                $settled++;
            }
        }

        $this->info("確定したオークション: {$settled} 件");

        return self::SUCCESS;
    }

    /**
     * 1 件のオークションを排他ロック下で確定する。既に確定済み、または
     * 条件を満たさなくなっていた場合は何もせず null を返す。
     *
     * 通知の送信 (ネットワーク I/O を伴う broadcast) はロックを長く握らないよう
     * トランザクションの外 (notify) に切り出している。
     */
    private function settle(int $auctionId): ?Auction
    {
        return DB::transaction(static function () use ($auctionId): ?Auction {
            $auction = Auction::query()->lockForUpdate()->find($auctionId);

            if ($auction === null || $auction->isSettled() || $auction->ends_at->isFuture()) {
                return null;
            }

            $auction->forceFill(['settled_at' => now()])->save();

            return $auction;
        });
    }

    /**
     * 確定したオークションの出品者・落札者へ通知する。
     */
    private function notify(Auction $auction): void
    {
        $auction->loadMissing('seller', 'currentWinner');

        $auction->seller->notify(new AuctionSettled($auction));

        if ($auction->currentWinner !== null) {
            $auction->currentWinner->notify(new AuctionSettled($auction));
        }
    }
}
