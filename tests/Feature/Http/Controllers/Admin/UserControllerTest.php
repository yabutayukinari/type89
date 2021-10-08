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
    public function testIndex_Viewが表示されていること()
    {
        $users = User::factory()->count(3)->create();

        $response = $this->get(route('user.index'));

        $response->assertOk();
        $response->assertViewIs('admin.user.index');
        $response->assertViewHas('users');
    }


    /**
     *
     */
    public function testShow_displays_view()
    {
        $user = User::factory()->create();

        $response = $this->get(route('user.show', $user));

        $response->assertOk();
        $response->assertViewIs('admin.user.show');
        $response->assertViewHas('user');
    }
}
