<?php declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Front;

use App\Http\Controllers\Front\ContactController;
use App\Mail\Contact\ContactCompleteToUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * @see ContactController
 */
class ContactControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * お問い合わせ入力画面表示テスト
     */
    public function testInput(): void
    {
        $response = $this->get(route('contact_input'));
        $response->assertOk();
        $response->assertViewIs('front.contact.input');
    }

    /**
     * [正常系]お問い合わせ送信テスト
     * 条件: 正常な入力値
     * 想定結果:
     * - 送信完了画面表示
     * - Contactsテーブルレコード追加
     * - 入力されたメールアドレスにメールを送信
     */
    public function testComplete(): void
    {
        Mail::fake();
        $postData = [
            'name' => '山田太郎',
            'name_kana' => 'やまだたろう',
            'email' => 'test@example.com',
            'body' => 'お問い合わせ内容'
        ];

        Mail::assertNotSent(ContactCompleteToUser::class);
        $response = $this->post(route('contact_complete', $postData));

        $response->assertOk();
        $response->assertViewIs('front.contact.complete');
        $this->assertDatabaseHas('contacts', $postData);
        Mail::assertSent(ContactCompleteToUser::class);
    }

    /**
     * [異常系]お問い合わせ送信テスト
     * 条件: 不正な入力値
     * 想定結果:
     * - 入力画面表示
     */
    public function testCompleteWithValidate():void
    {
        Mail::fake();
        $postData = [
            'name' => '',
            'name_kana' => 'やまだたろう',
            'email' => 'test@example.com',
            'body' => 'お問い合わせ内容'
        ];

        Mail::assertNotSent(ContactCompleteToUser::class);

        $response = $this->post(route('contact_complete', $postData));

        $response->assertStatus(302);
        $this->assertDatabaseMissing('contacts', $postData);
        Mail::assertNotSent(ContactCompleteToUser::class);
    }
}
