<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Performance;
use App\Models\SeatInventory;
use App\Models\Show;
use Database\Seeders\TicketSaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketSaleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_open_demo_performance_with_limited_seats(): void
    {
        $this->seed(TicketSaleSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'test_user@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'demo2@example.com']);
        $this->assertDatabaseHas('shows', [
            'title' => 'MIDNIGHT CIRCUIT VOL.12',
            'venue_label' => 'CLUB BAY / 東京',
        ]);
        $this->assertDatabaseHas('seat_inventories', [
            'capacity' => 8,
            'remaining_seats' => 8,
        ]);
        $this->assertDatabaseHas('admins', ['email' => 'admin@example.com']);

        $this->getJson('/api/performances')
            ->assertOk()
            ->assertJsonPath('data.0.sale_status', 'open')
            ->assertJsonPath('data.0.remaining_seats', 8)
            ->assertJsonPath('data.0.show.title', 'MIDNIGHT CIRCUIT VOL.12');
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(TicketSaleSeeder::class);
        $this->seed(TicketSaleSeeder::class);

        $this->assertSame(1, Show::query()->count());
        $this->assertSame(1, Performance::query()->count());
        $this->assertSame(1, SeatInventory::query()->count());
    }
}
