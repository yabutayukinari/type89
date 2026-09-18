<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Enums\SlotEventType;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\Performance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withHeader('Origin', 'http://localhost:3000');
    }

    public function test_organizer_inventory_requires_admin_authentication(): void
    {
        $performance = Performance::factory()->create();

        $this->getJson('/api/admin/performances')->assertStatus(401);
        $this->getJson("/api/admin/performances/{$performance->id}")->assertStatus(401);

        $user = User::factory()->create();
        $this->actingAs($user)
            ->getJson('/api/admin/performances')
            ->assertStatus(401);
    }

    public function test_organizer_inventory_lists_holds_confirms_and_releases(): void
    {
        $admin = Admin::factory()->systemAdmin()->create();
        $performance = Performance::factory()->withCapacity(2)->create();
        $holder = User::factory()->create(['name' => '仮確保ユーザー']);
        $confirmer = User::factory()->create(['name' => '確定ユーザー']);

        $this->actingAs($holder)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($confirmer)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($confirmer)->postJson("/api/performances/{$performance->id}/queue/confirm")->assertOk();
        $this->actingAs($holder)->postJson("/api/performances/{$performance->id}/queue/cancel")->assertOk();

        $response = $this->actingAs($admin, 'admin')
            ->getJson("/api/admin/performances/{$performance->id}")
            ->assertOk();

        $response->assertJsonPath('data.held_count', 0)
            ->assertJsonPath('data.confirmed_count', 1)
            ->assertJsonPath('data.waiting_count', 0)
            ->assertJsonPath('data.remaining_seats', 1)
            ->assertJsonPath('data.capacity', 2)
            ->assertJsonPath('data.hold_ttl_seconds', 180)
            ->assertJsonPath('data.current_slots.0.status', 'confirmed')
            ->assertJsonPath('data.current_slots.0.user.name', '確定ユーザー');

        $events = $response->json('data.events');
        $this->assertIsArray($events);
        $types = array_column($events, 'type');
        $this->assertContains(SlotEventType::Held->value, $types);
        $this->assertContains(SlotEventType::Confirmed->value, $types);
        $this->assertContains(SlotEventType::Released->value, $types);
    }

    public function test_organizer_index_returns_performances(): void
    {
        $admin = Admin::factory()->generalAdmin()->create();
        Performance::factory()->create();

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/performances')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.hold_ttl_seconds', 180);
    }
}
