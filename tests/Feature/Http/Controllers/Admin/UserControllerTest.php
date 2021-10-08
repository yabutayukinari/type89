<?php declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Admin\UserController
 */
class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ユーザー一覧表示テスト
     */
    public function testIndex(): void{
        User::factory()->count(3)->create();

        $response = $this->get(route('user.index'));

        $response->assertOk();
        $response->assertViewIs('admin.user.index');
        $response->assertViewHas('users');
    }


    /**
     * ユーザー詳細表示テスト
     */
    public function testShow(): void
    {
        $user = User::factory()->create();

        $response = $this->get(route('user.show', $user));

        $response->assertOk();
        $response->assertViewIs('admin.user.show');
        $response->assertViewHas('user');
    }
}
