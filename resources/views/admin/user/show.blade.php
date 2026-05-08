@extends('admin.layouts.admin_layouts')

@section('title')
    ユーザー詳細
@endsection

@section('content')
    <div class="space-y-6 max-w-3xl">
        <!-- アラート -->
        @if (session('status'))
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm">更新完了しました。</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
                <span class="text-sm">入力値のエラーがあります。</span>
            </div>
        @endif

        <!-- 基本情報カード -->
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] overflow-hidden">
            <div class="px-6 py-4 border-b border-[var(--color-border)]">
                <h2 class="font-semibold">基本情報</h2>
            </div>
            <div class="p-6">
                <form action="{{ route('admin_user_update', [$user->id]) }}" method="post" novalidate>
                    @csrf
                    <div class="space-y-5">
                        <!-- 名前 -->
                        <div class="grid grid-cols-12 gap-4 items-start">
                            <label for="inputName" class="col-span-3 text-sm font-medium py-2.5">
                                名前
                            </label>
                            <div class="col-span-6">
                                <input type="text"
                                       name="name"
                                       id="inputName"
                                       class="w-full px-4 py-2.5 text-sm rounded-lg border border-[var(--color-border)] bg-[var(--color-background)] focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20 transition-colors @error('name') border-red-500 focus:border-red-500 focus:ring-red-500/20 @enderror"
                                       value="{{ old('name', $user->name) }}">
                                @error('name')
                                    <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="col-span-3">
                                <span class="text-xs text-[var(--color-muted)] py-2.5 inline-block">100文字まで</span>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="grid grid-cols-12 gap-4 items-start">
                            <label for="email" class="col-span-3 text-sm font-medium py-2.5">
                                Email
                            </label>
                            <div class="col-span-6">
                                <input type="email"
                                       name="email"
                                       id="email"
                                       class="w-full px-4 py-2.5 text-sm rounded-lg border border-[var(--color-border)] bg-[var(--color-background)] focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20 transition-colors @error('email') border-red-500 focus:border-red-500 focus:ring-red-500/20 @enderror"
                                       value="{{ old('email', $user->email) }}">
                                @error('email')
                                    <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- 送信ボタン -->
                        <div class="pt-4">
                            <button type="submit"
                                    class="px-6 py-2.5 text-sm font-medium rounded-lg bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-hover)] transition-colors">
                                更新
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- その他情報カード -->
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] overflow-hidden">
            <div class="px-6 py-4 border-b border-[var(--color-border)]">
                <h2 class="font-semibold">その他</h2>
            </div>
            <div class="p-6">
                <dl class="space-y-4">
                    <div class="grid grid-cols-12 gap-4">
                        <dt class="col-span-3 text-sm font-medium text-[var(--color-muted)]">最終ログイン日時</dt>
                        <dd class="col-span-9 text-sm">{{ $user->last_login_at }}</dd>
                    </div>
                    <div class="grid grid-cols-12 gap-4">
                        <dt class="col-span-3 text-sm font-medium text-[var(--color-muted)]">作成日時</dt>
                        <dd class="col-span-9 text-sm">{{ $user->created_at }}</dd>
                    </div>
                    <div class="grid grid-cols-12 gap-4">
                        <dt class="col-span-3 text-sm font-medium text-[var(--color-muted)]">更新日時</dt>
                        <dd class="col-span-9 text-sm">{{ $user->updated_at }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- 戻るボタン -->
        <div>
            <a href="{{ route('admin_user_index') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-lg border border-[var(--color-border)] text-[var(--color-muted)] hover:bg-[var(--color-surface)] hover:text-[var(--color-foreground)] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                戻る
            </a>
        </div>
    </div>
@endsection
