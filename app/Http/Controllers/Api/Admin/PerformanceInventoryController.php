<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizerInventoryResource;
use App\Http\Resources\PerformanceResource;
use App\Models\Performance;
use App\Services\QueueBroadcast;
use App\Services\TicketQueueService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceInventoryController extends Controller
{
    public function __construct(
        private readonly TicketQueueService $ticketQueue,
        private readonly QueueBroadcast $broadcast,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $performances = Performance::query()
            ->with(['show', 'seatInventory'])
            ->orderBy('starts_at')
            ->get();

        return PerformanceResource::collection($performances);
    }

    public function show(Performance $performance): JsonResource
    {
        $mutation = $this->ticketQueue->expireHolds($performance);
        $this->broadcast->dispatch($mutation);

        return new OrganizerInventoryResource(
            $this->ticketQueue->organizerInventory($performance),
        );
    }
}
