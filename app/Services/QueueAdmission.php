<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;

final readonly class QueueAdmission
{
    /**
     * @param list<PurchaseSlot> $newlyAssignedSlots
     */
    public function __construct(
        public Performance $performance,
        public ?QueueEntry $queueEntry,
        public ?PurchaseSlot $purchaseSlot,
        public array $newlyAssignedSlots = [],
    ) {
    }
}
