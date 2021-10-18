@extends('front.layouts.layouts')
@section('title')
    お問い合わせ
@endsection
@section('content')
    <div class="col-6 offset-3">
        <form method="post" action="{{ route('contact_confirm') }}">
            @csrf
            <div class="mb-3 input-group @error('name') has-validation @enderror">
                <input type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       name="name"
                       aria-describedby="validationNameFeedback"
                       placeholder="お名前（必須）" value="{{ old('name') }}">
                @error('name')
                <div id="validationNameFeedback" class="invalid-feedback">
                    {{ $message }}
                </div>
                @enderror
            </div>
            <div class="mb-3 input-group @error('name_kana') has-validation @enderror">
                <input type="text"
                       class="form-control @error('name_kana') is-invalid @enderror"
                       name="name_kana"
                       aria-describedby="validationNameKanaFeedback"
                       placeholder="お名前ふりなが（必須）" value="{{ old('name_kana') }}">
                @error('name_kana')
                <div id="validationNameKanaFeedback" class="invalid-feedback">
                    {{ $message }}
                </div>
                @enderror
            </div>
            <div class="mb-3 input-group @error('email') has-validation @enderror">
                <input type="text"
                       class="form-control @error('email') is-invalid @enderror"
                       name="email"
                       aria-describedby="validationEmailFeedback"
                       placeholder="メールアドレス（必須）"
                       value="{{ old('email') }}">
                @error('email')
                <div id="validationEmailFeedback" class="invalid-feedback">
                    {{ $message }}
                </div>
                @enderror
            </div>
            <div class="mb-4 input-group @error('contact_body') has-validation @enderror">
                <textarea
                    class="form-control @error('contact_body') is-invalid @enderror"
                    name="contact_body"
                    aria-describedby="validationContactBodyFeedback"
                    rows="5" placeholder="お問い合わせ内容(必須)">{!! old('contact_body') !!}</textarea>
                @error('contact_body')
                <div id="validationContactBodyFeedback" class="invalid-feedback">
                    {{ $message }}
                </div>
                @enderror
            </div>
            <div class="text-center pt-4 col-md-6 offset-md-3">
                <button type="submit" class="btn btn-primary">確認</button>
            </div>
        </form>
    </div>
@endsection
