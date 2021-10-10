@extends('admin.layouts.admin_layouts')
@section('title')
    ユーザー詳細
@endsection

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="row">
                @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        更新完了しました。
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        入力値のエラーがあります。
                    </div>
                @endif
            </div>
            <div class="card mb-3">
                <div class="card-header">
                    基本情報
                </div>
                <div class="card-body">
                    <form action="{{route('admin_user_update', [$user->id])}}" method="post" class="" novalidate>
                        @csrf
                        {{-- nickname --}}
                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-1">
                                <label for="inputNickname" class="col-form-label">ニックネーム</label>
                            </div>
                            <div class="col-6">
                                <div class="input-group @error('nickname') has-validation @enderror">
                                    <input type="text"
                                           name="nickname"
                                           id="inputNickname"
                                           class="form-control @error('nickname') is-invalid @enderror"
                                           aria-describedby="nicknameHelpInline validationNickNameFeedback"
                                           value="{{ old('nickname') ? old('nickname') : $user->nickname }}"
                                    >
                                    @error('nickname')
                                    <div id="validationNickNameFeedback" class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-2">
                            <span id="nicknameHelpInline" class="form-text">
                                100文字まで
                            </span>
                            </div>
                        </div>
                        {{-- email --}}
                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-1">
                                <label for="email" class="col-form-label">email</label>
                            </div>
                            <div class="col-6">
                                <div class="input-group @error('email') has-validation @enderror">

                                </div>
                                <input type="text"
                                       id="email"
                                       name="email"
                                       class="form-control @error('email') is-invalid @enderror"
                                       aria-describedby="validationEmailFeedback"
                                       value="{{ old('email') ? old('email') : $user->email }}"
                                >
                                @error('email')
                                <div id="validationEmailFeedback" class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                        </div>
                        <div class="row">
                            <div class="d-grid gap-2 col-4 mx-auto">
                                <button type="submit" class="btn btn-primary">更新</button>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    その他
                </div>
                <div class="card-body">
                    <form>
                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-3">
                                <label for="lastLoginAt" class="col-form-label">最終ログイン日時</label>
                            </div>
                            <div class="col-7">
                                <span id="lastLoginAt">{{ $user->last_login_at }}</span>
                            </div>
                        </div>
                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-3">
                                <label for="lastLoginAt" class="col-form-label">作成日時</label>
                            </div>
                            <div class="col-7">
                                <span id="lastLoginAt">{{ $user->created_at }}</span>
                            </div>
                        </div>
                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-3">
                                <label for="lastLoginAt" class="col-form-label">更新日時</label>
                            </div>
                            <div class="col-7">
                                <span id="lastLoginAt">{{ $user->updated_at }}</span>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="d-grid gap-2 col-3 mx-auto">
            <button type="submit" class="btn btn-secondary">戻る</button>
        </div>
    </div>
@endsection
