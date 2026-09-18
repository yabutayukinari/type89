<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Performance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withHeader('Origin', 'http://localhost:3000');
    }

    public function test_index_returns_performances(): void
    {
        Performance::factory()->count(2)->create();

        $response = $this->getJson('/api/performances');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_show_returns_performance_with_inventory(): void
    {
        $performance = Performance::factory()->withCapacity(8)->create([
            'price' => 6500,
        ]);
        $inventory = $performance->seatInventory;
        $this->assertNotNull($inventory);

        $response = $this->getJson("/api/performances/{$performance->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $performance->id)
            ->assertJsonPath('data.price', 6500)
            ->assertJsonPath('data.sale_status', 'open')
            ->assertJsonPath('data.capacity', 8)
            ->assertJsonPath('data.remaining_seats', 8)
            ->assertJsonPath('data.inventory_updated_at', $inventory->updated_at->toIso8601String())
            ->assertJsonPath('data.hold_ttl_seconds', 180)
            ->assertJsonPath('data.show.title', $performance->show->title)
            ->assertJsonPath('data.show.venue_label', $performance->show->venue_label);
    }

    public function test_show_returns_upcoming_status_before_sale_opens(): void
    {
        $performance = Performance::factory()->upcoming()->create();

        $response = $this->getJson("/api/performances/{$performance->id}");

        $response->assertOk()
            ->assertJsonPath('data.sale_status', 'upcoming');
    }

    public function test_show_returns_closed_status_after_sale_closes(): void
    {
        $performance = Performance::factory()->closed()->create();

        $response = $this->getJson("/api/performances/{$performance->id}");

        $response->assertOk()
            ->assertJsonPath('data.sale_status', 'closed');
    }
}
