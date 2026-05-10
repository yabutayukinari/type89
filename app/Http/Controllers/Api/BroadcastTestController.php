<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\PingBroadcasted;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class BroadcastTestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $event = new PingBroadcasted(
            message: 'pong from Laravel',
            emittedAt: Carbon::now()->toIso8601String(),
        );

        broadcast($event)->toOthers();

        return response()->json([
            'broadcast' => 'public.ping',
            'event' => 'ping',
            'payload' => $event->broadcastWith(),
        ]);
    }
}
