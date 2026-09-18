<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\SlotEvent;
use Illuminate\Support\Collection;

final readonly class OrganizerInventory
{
    /**
     * @param  Collection<int, PurchaseSlot>  $currentSlots
     * @param  Collection<int, SlotEvent>  $events
     */
    public function __construct(
        public Performance $performance,
        public int $heldCount,
        public int $confirmedCount,
        public int $waitingCount,
        public Collection $currentSlots,
        public Collection $events,
    ) {}
}
