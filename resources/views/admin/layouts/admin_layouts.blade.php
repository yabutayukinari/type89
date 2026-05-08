<!doctype html>
<html lang="ja" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>
        @hasSection('title')
            @yield('title') - {{ config('app.name') }}
        @else
            {{ config('app.name') }}
        @endif
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <div class="flex h-full">
        <!-- サイドバー -->
        <aside class="w-64 shrink-0 border-r border-[var(--color-border)] bg-[var(--color-surface)] flex flex-col h-screen sticky top-0">
            <!-- ロゴ -->
            <div class="h-16 flex items-center px-6 border-b border-[var(--color-border)] shrink-0">
                <span class="text-lg font-semibold">Admin</span>
            </div>

            <!-- ナビゲーション -->
            <nav class="p-4 space-y-1 flex-1 overflow-auto">
                @if(request()->routeIs('admin_user*'))
                    <span class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium bg-[var(--color-primary)] text-white cursor-default">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                        </svg>
                        ユーザー一覧
                    </span>
                @else
                    <a href="{{ route('admin_user_index') }}"
                       class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors text-[var(--color-muted)] hover:bg-[var(--color-border)] hover:text-[var(--color-foreground)]">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                        </svg>
                        ユーザー一覧
                    </a>
                @endif
                <a href="#"
                   class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors text-[var(--color-muted)] hover:bg-[var(--color-border)] hover:text-[var(--color-foreground)]">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                    管理者一覧
                </a>
                <a href="#"
                   class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors text-[var(--color-muted)] hover:bg-[var(--color-border)] hover:text-[var(--color-foreground)]">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                    </svg>
                    お知らせ一覧
                </a>
            </nav>

            <!-- ダークモードトグル -->
            <div class="p-4 border-t border-[var(--color-border)] shrink-0">
                <button onclick="toggleDarkMode()"
                        class="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors text-[var(--color-muted)] hover:bg-[var(--color-border)] hover:text-[var(--color-foreground)]">
                    <svg class="w-5 h-5 shrink-0 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
                    </svg>
                    <svg class="w-5 h-5 shrink-0 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                    </svg>
                    <span class="dark:hidden">ダークモード</span>
                    <span class="hidden dark:inline">ライトモード</span>
                </button>
            </div>
        </aside>

        <!-- メインコンテンツ -->
        <main class="flex-1 min-h-screen overflow-auto">
            <div class="max-w-6xl mx-auto px-8 py-8">
                @hasSection('title')
                    <h1 class="text-2xl font-semibold mb-8">@yield('title')</h1>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
