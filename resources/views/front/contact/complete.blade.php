@extends('front.layouts.layouts')
@section('title')
    お問い合わせ
@endsection
@section('content')
    <div class="col-6 offset-3">
        お問い合わせいただきありがとうございます。
        確認のため、入力いただいたメールアドレスに自動返信メールをお送りします。
    </div>
    <div class="col-6 offset-3">
        <a href="{{ route('home') }}">TOPへ戻る</a>
    </div>

@endsection
