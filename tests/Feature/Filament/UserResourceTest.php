<?php declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\Admin;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Admin $admin */
        $admin = Admin::factory()->generalAdmin()->create([
            'email_verified_at' => now(),
        ]);
        $this->admin = $admin;
    }

    public function testCanRenderListPage(): void
    {
        $this->actingAs($this->admin, 'admin');

        $this->get(UserResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function testCanRenderCreatePage(): void
    {
        $this->actingAs($this->admin, 'admin');

        $this->get(UserResource::getUrl('create'))
            ->assertSuccessful();
    }

    public function testCanRenderEditPage(): void
    {
        $this->actingAs($this->admin, 'admin');

        $user = User::factory()->create();

        $this->get(UserResource::getUrl('edit', ['record' => $user]))
            ->assertSuccessful();
    }

    public function testCanRenderViewPage(): void
    {
        $this->actingAs($this->admin, 'admin');

        /** @var User $user */
        $user = User::factory()->create();

        $this->get(UserResource::getUrl('view', ['record' => $user]))
            ->assertSuccessful();
    }

    public function testCanViewUser(): void
    {
        $this->actingAs($this->admin, 'admin');

        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'testview@example.com',
        ]);

        Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
            ->assertSuccessful();
    }

    public function testCanListUsers(): void
    {
        $this->actingAs($this->admin, 'admin');

        $users = User::factory()->count(3)->create();

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords($users);
    }

    public function testCanCreateUser(): void
    {
        $this->actingAs($this->admin, 'admin');

        $newUserData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ];

        Livewire::test(CreateUser::class)
            ->fillForm($newUserData)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
        ]);
    }

    public function testCanUpdateUser(): void
    {
        $this->actingAs($this->admin, 'admin');

        /** @var User $user */
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function testCanDeleteUser(): void
    {
        $this->actingAs($this->admin, 'admin');

        /** @var User $user */
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
    }

    public function testCanValidateCreateInput(): void
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => '',
                'email' => 'invalid-email',
                'password' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name', 'email', 'password']);
    }
}
