<?php declare(strict_types=1);

namespace App\Http\Controllers\Front;

use App\Http\Requests\ContactRequest;
use App\Mail\Contact\ContactCompleteToUser;
use App\Models\Contact;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * お問い合わせ
 */
class ContactController
{
    /**
     * 問い合わせ登録
     * メール送信
     * 完了画面
     */
    public function complete(ContactRequest $request): View
    {
        $contact = Contact::create($request->all());
        $request->session()->regenerateToken();
        Mail::send(new ContactCompleteToUser($contact));
        return view('front.contact.complete');
    }
}
