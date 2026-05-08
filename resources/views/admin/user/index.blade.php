@extends('admin.layouts.admin_layouts')

@section('title')
    ユーザー一覧
@endsection

@section('content')
    <div class="space-y-6">
        <!-- アラート -->
        <div class="flex items-center gap-3 px-4 py-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
            </svg>
            <span class="text-sm">A simple primary alert—check it out!</span>
        </div>

        <!-- テーブル -->
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-[var(--color-border)]">
                        <th class="px-6 py-4 text-left text-xs font-semibold text-[var(--color-muted)] uppercase tracking-wider">#</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-[var(--color-muted)] uppercase tracking-wider">Name</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-[var(--color-muted)] uppercase tracking-wider">Email</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-[var(--color-muted)] uppercase tracking-wider">Last Login</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-[var(--color-muted)] uppercase tracking-wider">Created</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-[var(--color-muted)] uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-border)]">
                    @foreach($users as $user)
                        <tr class="hover:bg-[var(--color-background)] transition-colors">
                            <td class="px-6 py-4 text-sm font-medium">{{ $user->id }}</td>
                            <td class="px-6 py-4 text-sm">{{ $user->name }}</td>
                            <td class="px-6 py-4 text-sm text-[var(--color-muted)]">{{ $user->email }}</td>
                            <td class="px-6 py-4 text-sm text-[var(--color-muted)]">{{ $user->last_login_at }}</td>
                            <td class="px-6 py-4 text-sm text-[var(--color-muted)]">{{ $user->created_at }}</td>
                            <td class="px-6 py-4">
                                <a href="{{ route('admin_user_show', [$user->id]) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-[var(--color-primary)] text-[var(--color-primary)] hover:bg-[var(--color-primary)] hover:text-white transition-colors">
                                    詳細
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- ペジネーション -->
        <div class="flex justify-center">
            {{ $users->onEachSide(5)->links() }}
        </div>
    </div>
@endsection
