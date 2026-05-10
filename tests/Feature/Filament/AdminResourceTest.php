<?php declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\AdminRole;
use App\Filament\Resources\AdminResource;
use App\Filament\Resources\AdminResource\Pages\CreateAdmin;
use App\Filament\Resources\AdminResource\Pages\EditAdmin;
use App\Filament\Resources\AdminResource\Pages\ListAdmins;
use App\Filament\Resources\AdminResource\Pages\ViewAdmin;
use App\Models\Admin;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminResourceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Admin $admin */
        $admin = Admin::factory()->systemAdmin()->create([
            'email_verified_at' => now(),
        ]);
        $this->systemAdmin = $admin;
    }

    public function testSystemAdminCanRenderListPage(): void
    {
        $this->actingAs($this->systemAdmin, 'admin');

        $this->get(AdminResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function testSystemAdminCanRenderCreatePage(): void
    {
        $this->actingAs($this->systemAdmin, 'admin');

        $this->get(AdminResource::getUrl('create'))
            ->assertSuccessful();
    }

    public function testSystemAdminCanRenderEditPage(): void
    {
        $this->actingAs($this->systemAdmin, 'admin');

        $target = Admin::factory()->generalAdmin()->create();

        $this->get(AdminResource::getUrl('edit', ['record' => $target]))
            ->assertSuccessful();
    }

    public function testSystemAdminCanRenderViewPage(): void
    {
        $this->actingAs($this->systemAdmin, 'admin');

        $target = Admin::factory()->generalAdmin()->create();

        $this->get(AdminResource::getUrl('view', ['record' => $target]))
            ->assertSuccessful();
    }

    public function testSystemAdminCanListAdmins(): void
    {
        $this->actingAs($this->systemAdmin, 'admin');

        $admins = Admin::factory()->count(3)->generalAdmin()->create();

        Livewire::test(ListAdmins::class)
            ->assertCanSeeTableRecords($admins);
    }

    public function testSystemAdminCanCreateAdmin(): void
    {
        $this->actingAs($this->systemAdmin, 'admin');

        Livewire::test(CreateAdmin::class)
            ->fillForm([
                'name' => 'New Admin',
                'email' => 'newadmin@example.com',
                'role' => AdminRole::GeneralAdmin->value,
                'password' => 'password123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('admins', [
            'email' => 'newadmin@example.com',
            'role' => AdminRole::GeneralAdmin->value,
        ]);
    }

    public function testSystemAdminCanUpdateAdminRole(): void
    {
        $this->actingAs($this->systemAdmin, 'admin');

        $target = Admin::factory()->generalAdmin()->create();

        Livewire::test(EditAdmin::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                'role' => AdminRole::SystemAdmin->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('admins', [
            'id' => $target->id,
            'role' => AdminRole::SystemAdmin->value,
        ]);
    }

    public function testSystemAdminCanDeleteAdmin(): void
    {
        $this->actingAs($this->systemAdmin, 'admin');

        $target = Admin::factory()->generalAdmin()->create();

        Livewire::test(EditAdmin::class, ['record' => $target->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted('admins', [
            'id' => $target->id,
        ]);
    }

    public function testGeneralAdminCannotAccessIndex(): void
    {
        /** @var Admin $generalAdmin */
        $generalAdmin = Admin::factory()->generalAdmin()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($generalAdmin, 'admin');

        $this->get(AdminResource::getUrl('index'))
            ->assertForbidden();
    }

    public function testGeneralAdminCannotAccessCreate(): void
    {
        /** @var Admin $generalAdmin */
        $generalAdmin = Admin::factory()->generalAdmin()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($generalAdmin, 'admin');

        $this->get(AdminResource::getUrl('create'))
            ->assertForbidden();
    }

    public function testUnauthenticatedUserIsRedirected(): void
    {
        $this->get(AdminResource::getUrl('index'))
            ->assertRedirect();
    }
}
