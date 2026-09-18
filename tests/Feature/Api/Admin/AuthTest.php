<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Enums\AdminRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\User;
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

    public function test_login_succeeds_and_returns_admin_resource(): void
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

    public function test_login_fails_with_invalid_credentials(): void
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

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/me');

        $response->assertStatus(401);
    }

    public function test_me_returns_current_admin(): void
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

    public function test_logout_invalidates_admin_session(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->postJson('/api/admin/logout');

        $response->assertNoContent();
        $this->assertGuest('admin');
    }

    public function test_user_session_does_not_grant_admin_access(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/admin/me');

        $response->assertStatus(401);
    }
}
