<?php declare(strict_types=1);

namespace App\Http\Controllers\Front;

use App\Http\Requests\ContactRequest;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

/**
 * お問い合わせ画面
 */
class ContactController
{
    /**
     * 入力画面
     */
    public function input(): View
    {
        return view('front.contact.input');
    }

    /**
     * 確認画面
     */
    public function confirm(ContactRequest $request): View
    {
        // 他のSessionのkeyと被るため、key名contact.nameとかのほうが良さげ
        $contact = $request->all();
        Session::put($contact);
        return view('front.contact.confirm', compact('contact'));
    }

    /**
     * 入力画面に戻る
     *
     * @return RedirectResponse
     */
    public function returnInput(): RedirectResponse
    {
        $inputs = [
            'name' => Session::pull('name'),
            'name_kana' => Session::pull('name_kana'),
            'email' => Session::pull('email'),
            'contact_body' => Session::pull('contact_body')
        ];
        return redirect()->route('contact_input')->withInput($inputs);
    }

    /**
     * 完了画面
     */
    public function complete(Request $request): View
    {
        Contact::create([
            'name' => Session::pull('name'),
            'name_kana' => Session::pull('name_kana'),
            'email' => Session::pull('email'),
            'body' => Session::pull('contact_body')
        ]);

        $request->session()->regenerateToken();
        return view('front.contact.complete');
    }
}
