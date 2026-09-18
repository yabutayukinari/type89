<?php declare(strict_types=1);

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

    public function testIndexReturnsPerformances(): void
    {
        Performance::factory()->count(2)->create();

        $response = $this->getJson('/api/performances');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function testShowReturnsPerformanceWithInventory(): void
    {
        $performance = Performance::factory()->withCapacity(8)->create([
            'price' => 6500,
        ]);

        $response = $this->getJson("/api/performances/{$performance->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $performance->id)
            ->assertJsonPath('data.price', 6500)
            ->assertJsonPath('data.sale_status', 'open')
            ->assertJsonPath('data.capacity', 8)
            ->assertJsonPath('data.remaining_seats', 8)
            ->assertJsonPath('data.show.title', $performance->show->title)
            ->assertJsonPath('data.show.venue_label', $performance->show->venue_label);
    }

    public function testShowReturnsUpcomingStatusBeforeSaleOpens(): void
    {
        $performance = Performance::factory()->upcoming()->create();

        $response = $this->getJson("/api/performances/{$performance->id}");

        $response->assertOk()
            ->assertJsonPath('data.sale_status', 'upcoming');
    }

    public function testShowReturnsClosedStatusAfterSaleCloses(): void
    {
        $performance = Performance::factory()->closed()->create();

        $response = $this->getJson("/api/performances/{$performance->id}");

        $response->assertOk()
            ->assertJsonPath('data.sale_status', 'closed');
    }
}
