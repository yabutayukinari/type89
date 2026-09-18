<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\QueueAdmissionResource;
use App\Models\Performance;
use App\Models\User;
use App\Services\QueueBroadcast;
use App\Services\TicketQueueService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class PerformanceQueueController extends Controller
{
    public function __construct(
        private readonly TicketQueueService $ticketQueue,
        private readonly QueueBroadcast $broadcast,
    ) {}

    public function show(Performance $performance): JsonResource
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $admission = $this->ticketQueue->status($performance, $user);
        $this->broadcast->dispatch($admission);

        return new QueueAdmissionResource($admission);
    }

    public function store(Performance $performance): JsonResource
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $admission = $this->ticketQueue->join($performance, $user);
        $this->broadcast->dispatch($admission, notifyActor: true);

        return new QueueAdmissionResource($admission);
    }

    public function confirm(Performance $performance): JsonResource
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $admission = $this->ticketQueue->confirm($performance, $user);
        $this->broadcast->dispatch($admission, notifyActor: true);

        return new QueueAdmissionResource($admission);
    }

    public function cancel(Performance $performance): JsonResource
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $admission = $this->ticketQueue->cancel($performance, $user);
        $this->broadcast->dispatch($admission, notifyActor: true);

        return new QueueAdmissionResource($admission);
    }
}
