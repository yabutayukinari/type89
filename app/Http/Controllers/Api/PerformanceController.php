<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PerformanceResource;
use App\Models\Performance;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceController extends Controller
{
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
        $performance->load(['show', 'seatInventory']);

        return new PerformanceResource($performance);
    }
}
