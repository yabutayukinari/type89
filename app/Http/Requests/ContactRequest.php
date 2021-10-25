<?php declare(strict_types=1);


namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|max:100',
            'name_kana' => 'required|max:200',
            'email' => 'required|email:rfc|max:100',
            'body' => 'required|max:65535'
        ];
    }

    /**
     * @return array
     */
    public function attributes()
    {
        return [
            'body' => 'お問い合わせ内容'
        ];
    }
}
