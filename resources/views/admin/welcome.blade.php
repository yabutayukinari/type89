<!doctype html>
<html lang="ja" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <div class="min-h-screen flex flex-col items-center justify-center p-8">
        <div class="max-w-md w-full text-center space-y-8">
            <!-- ロゴ -->
            <div class="flex justify-center">
                <div class="w-16 h-16 rounded-2xl bg-[var(--color-primary)] flex items-center justify-center">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
                    </svg>
                </div>
            </div>

            <!-- タイトル -->
            <div class="space-y-2">
                <h1 class="text-3xl font-bold">{{ config('app.name') }}</h1>
                <p class="text-[var(--color-muted)]">Admin Dashboard</p>
            </div>

            <!-- ナビゲーション -->
            <div class="space-y-3">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/home') }}"
                           class="block w-full px-6 py-3 text-sm font-medium rounded-lg bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-hover)] transition-colors">
                            ホームへ
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="block w-full px-6 py-3 text-sm font-medium rounded-lg bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-hover)] transition-colors">
                            ログイン
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}"
                               class="block w-full px-6 py-3 text-sm font-medium rounded-lg border border-[var(--color-border)] text-[var(--color-foreground)] hover:bg-[var(--color-surface)] transition-colors">
                                新規登録
                            </a>
                        @endif
                    @endauth
                @endif

                <a href="{{ route('admin_user_index') }}"
                   class="block w-full px-6 py-3 text-sm font-medium rounded-lg border border-[var(--color-border)] text-[var(--color-foreground)] hover:bg-[var(--color-surface)] transition-colors">
                    管理画面へ
                </a>
            </div>

            <!-- フッター -->
            <div class="pt-8">
                <p class="text-xs text-[var(--color-muted)]">
                    Laravel v{{ Illuminate\Foundation\Application::VERSION }} (PHP v{{ PHP_VERSION }})
                </p>
            </div>
        </div>

        <!-- ダークモードトグル -->
        <button onclick="toggleDarkMode()"
                class="fixed bottom-6 right-6 p-3 rounded-full bg-[var(--color-surface)] border border-[var(--color-border)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] transition-colors">
            <svg class="w-5 h-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
            </svg>
            <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
            </svg>
        </button>
    </div>
</body>
</html>
