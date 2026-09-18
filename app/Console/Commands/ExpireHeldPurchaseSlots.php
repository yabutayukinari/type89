<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PurchaseSlotStatus;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Services\QueueBroadcast;
use App\Services\TicketQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ExpireHeldPurchaseSlots extends Command
{
    protected $signature = 'tickets:expire-holds';

    protected $description = '仮確保の TTL を過ぎた枠を解放し、待機列の先頭へ FIFO で割り当てる';

    public function handle(TicketQueueService $ticketQueue, QueueBroadcast $broadcast): int
    {
        $performanceIds = PurchaseSlot::query()
            ->where('status', PurchaseSlotStatus::Held)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now())
            ->pluck('performance_id')
            ->unique()
            ->values();

        $expired = 0;
        foreach ($performanceIds as $performanceId) {
            $performance = Performance::query()->whereKey((int) $performanceId)->first();
            if (! $performance instanceof Performance) {
                continue;
            }

            $admission = $ticketQueue->expireHolds($performance);
            $broadcast->dispatch($admission);
            $expired += count($admission->releasedUserIds());
        }

        $this->info("期限切れにした仮確保: {$expired} 件");

        return self::SUCCESS;
    }
}
