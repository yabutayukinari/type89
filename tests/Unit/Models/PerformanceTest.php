<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\SaleStatus;
use App\Models\Performance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_status_is_upcoming_before_open(): void
    {
        $performance = Performance::factory()->upcoming()->create();

        $this->assertSame(SaleStatus::Upcoming, $performance->sale_status);
        $this->assertFalse($performance->isSaleOpen());
    }

    public function test_sale_status_is_open_during_window(): void
    {
        $performance = Performance::factory()->create([
            'sale_opens_at' => Carbon::now()->subMinute(),
            'sale_closes_at' => Carbon::now()->addHour(),
        ]);

        $this->assertSame(SaleStatus::Open, $performance->sale_status);
        $this->assertTrue($performance->isSaleOpen());
    }

    public function test_sale_status_is_closed_after_close(): void
    {
        $performance = Performance::factory()->closed()->create();

        $this->assertSame(SaleStatus::Closed, $performance->sale_status);
        $this->assertFalse($performance->isSaleOpen());
    }

    public function test_factory_creates_matching_seat_inventory(): void
    {
        $performance = Performance::factory()->withCapacity(5, 3)->create();

        $inventory = $performance->seatInventory;
        $this->assertNotNull($inventory);
        $this->assertSame(5, $inventory->capacity);
        $this->assertSame(3, $inventory->remaining_seats);
    }
}
