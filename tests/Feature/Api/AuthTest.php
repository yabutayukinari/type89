<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\VerifyCsrfToken;
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

    public function test_login_succeeds_and_returns_user_resource(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'email' => 'user@example.com',
                ],
            ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest();
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_me_returns_current_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/me');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/logout');

        $response->assertNoContent();
        $this->assertGuest();
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }
}
