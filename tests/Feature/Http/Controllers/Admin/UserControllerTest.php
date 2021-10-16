<?php declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\User;
use App\Repositories\Slack\SlackRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Mockery;
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
    public function testIndex(): void
    {
        User::factory()->count(3)->create();

        $response = $this->get(route('admin_user_index'));

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

        $response = $this->get(route('admin_user_show', $user));

        $response->assertOk();
        $response->assertViewIs('admin.user.show');
        $response->assertViewHas('user');
    }

    /**
     * ユーザー更新テスト
     */
    public function testUpdate(): void
    {
        Event::fake();
        $mock = Mockery::mock(SlackRepository::class);
        $mock->shouldReceive('notify')->once();
        App::instance(SlackRepository::class, $mock);

        $user = User::factory()->create();
        $response = $this->post(route('admin_user_update', $user), [
            'nickname' => 'テストユーザー', 'email' => 'test@exsample.com'
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('users', [
            'nickname' => 'テストユーザー',
            'email' => 'test@exsample.com'
        ]);
    }

    /**
     *
     */
    public function testUpdateValidateError(): void
    {
        $user = User::factory()->create();
        $response = $this->post(route('admin_user_update', $user), [
            'nickname' => Str::random(101), 'email' => 'aaa'
        ]);

        $response->assertStatus(302);
    }
}
