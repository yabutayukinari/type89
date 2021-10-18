@extends('front.layouts.layouts')
@section('title')
    お問い合わせ
@endsection
@section('content')
    <div class="col-6 offset-3">
        <form method="post" action="{{ route('contact_complete') }}">
            @csrf
            <div class="mb-3">
                <p>お名前: {{ $contact['name'] }}</p>
            </div>
            <div class="mb-3">
                <p>お名前ふりがな: {{ $contact['name_kana'] }}</p>
            </div>
            <div class="mb-3">
                <p>メールアドレス: {{ $contact['email'] }}</p>
            </div>
            <div class="mb-4">
                <p>お問い合わせ内容</p>
                <div>
                    {!! nl2br($contact['contact_body']) !!}
                </div>
            </div>
            <div>
                この内容でよろしいですか？
            </div>
            <div class="text-center pt-4 col-md-6 offset-md-3">
                <button type="submit" class="btn btn-primary">登録</button>
            </div>
            <div class="text-center pt-4 col-md-6 offset-md-3">
                <a href="{{ route('contact_return') }}">入力画面に戻る</a>
            </div>

        </form>
    </div>

@endsection
