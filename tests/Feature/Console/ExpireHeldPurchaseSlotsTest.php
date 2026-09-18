<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\QueueEntryStatus;
use App\Enums\SlotReleaseReason;
use App\Events\PurchaseSlotAssigned;
use App\Events\SeatsUpdated;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Performance;
use App\Models\PurchaseSlot;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ExpireHeldPurchaseSlotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withHeader('Origin', 'http://localhost:3000');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_command_expires_held_slots_and_fifo_admits_waiters(): void
    {
        Event::fake();

        $performance = Performance::factory()->withCapacity(1)->create();
        $holder = User::factory()->create();
        $waiter = User::factory()->create();

        $this->actingAs($holder)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($waiter)->postJson("/api/performances/{$performance->id}/queue")->assertOk();

        Carbon::setTestNow(Carbon::now()->addMinutes(5));

        $this->assertSame(0, Artisan::call('tickets:expire-holds'));
        $this->assertStringContainsString('期限切れにした仮確保: 1 件', Artisan::output());

        $this->assertDatabaseMissing('purchase_slots', ['user_id' => $holder->id]);
        $this->assertDatabaseHas('purchase_slots', [
            'user_id' => $waiter->id,
            'performance_id' => $performance->id,
        ]);
        $this->assertSame(
            QueueEntryStatus::Expired,
            QueueEntry::query()->where('user_id', $holder->id)->first()?->status,
        );
        $this->assertSame(0, $performance->seatInventory()->firstOrFail()->remaining_seats);
        Event::assertDispatched(SeatsUpdated::class);
        Event::assertDispatched(PurchaseSlotAssigned::class);
    }

    public function test_command_does_not_expire_confirmed_slots(): void
    {
        $performance = Performance::factory()->withCapacity(1)->create();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/api/performances/{$performance->id}/queue")->assertOk();
        $this->actingAs($user)->postJson("/api/performances/{$performance->id}/queue/confirm")->assertOk();

        Carbon::setTestNow(Carbon::now()->addMinutes(30));
        $this->assertSame(0, Artisan::call('tickets:expire-holds'));

        $this->assertSame(1, PurchaseSlot::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, Artisan::call('tickets:expire-holds'));
    }

    public function test_command_is_a_no_op_when_nothing_is_expired(): void
    {
        Performance::factory()->create();

        $this->assertSame(0, Artisan::call('tickets:expire-holds'));
        $this->assertStringContainsString('期限切れにした仮確保: 0 件', Artisan::output());
    }

    public function test_expired_holder_status_explains_ttl_not_a_silent_drop(): void
    {
        $performance = Performance::factory()->withCapacity(1)->create();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson("/api/performances/{$performance->id}/queue")->assertOk();

        Carbon::setTestNow(Carbon::now()->addMinutes(5));
        Artisan::call('tickets:expire-holds');

        $this->actingAs($user)
            ->getJson("/api/performances/{$performance->id}/queue")
            ->assertOk()
            ->assertJsonPath('data.slot_release.reason', SlotReleaseReason::Ttl->value)
            ->assertJsonPath('data.queue_entry.status', QueueEntryStatus::Expired->value)
            ->assertJsonPath('data.purchase_slot', null);
    }
}
