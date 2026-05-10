<?php declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Enums\AdminRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withHeader('Origin', 'http://localhost:3000');
    }

    public function testLoginSucceedsAndReturnsAdminResource(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => AdminRole::SystemAdmin,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $admin->id,
                    'email' => 'admin@example.com',
                    'role' => AdminRole::SystemAdmin->value,
                ],
            ]);

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function testLoginFailsWithInvalidCredentials(): void
    {
        Admin::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function testMeRequiresAuthentication(): void
    {
        $response = $this->getJson('/api/admin/me');

        $response->assertStatus(401);
    }

    public function testMeReturnsCurrentAdmin(): void
    {
        $admin = Admin::factory()->generalAdmin()->create();

        $response = $this->actingAs($admin, 'admin')->getJson('/api/admin/me');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $admin->id,
                    'email' => $admin->email,
                    'role' => AdminRole::GeneralAdmin->value,
                ],
            ]);
    }

    public function testLogoutInvalidatesAdminSession(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->postJson('/api/admin/logout');

        $response->assertNoContent();
        $this->assertGuest('admin');
    }

    public function testUserSessionDoesNotGrantAdminAccess(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/admin/me');

        $response->assertStatus(401);
    }
}
