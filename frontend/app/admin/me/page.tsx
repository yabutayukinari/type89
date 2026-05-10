'use client';

import { useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { logoutAdmin, useAdmin } from '@/lib/auth';

export default function AdminDashboardPage() {
  const router = useRouter();
  const { auth } = useAdmin();

  useEffect(() => {
    if (auth.state === 'unauthenticated') {
      router.replace('/admin/login');
    }
  }, [auth.state, router]);

  if (auth.state !== 'authenticated') {
    return (
      <main className="flex min-h-screen items-center justify-center p-8">
        <p className="text-zinc-600 dark:text-zinc-400">
          {auth.state === 'loading' ? '読み込み中...' : 'ログインへ移動します...'}
        </p>
      </main>
    );
  }

  const handleLogout = async () => {
    await logoutAdmin();
    router.push('/admin/login');
  };

  return (
    <main className="mx-auto mt-16 flex w-full max-w-md flex-col gap-4 rounded-lg border border-zinc-300 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
      <h1 className="text-2xl font-semibold">Admin ダッシュボード</h1>
      <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
        <dt className="text-zinc-500">id</dt>
        <dd>{auth.principal.id}</dd>
        <dt className="text-zinc-500">name</dt>
        <dd>{auth.principal.name}</dd>
        <dt className="text-zinc-500">email</dt>
        <dd>{auth.principal.email}</dd>
        <dt className="text-zinc-500">role</dt>
        <dd>{auth.principal.role}</dd>
      </dl>
      <button
        type="button"
        onClick={handleLogout}
        className="mt-4 rounded border border-zinc-300 px-4 py-2 text-base font-medium dark:border-zinc-700"
      >
        ログアウト
      </button>
    </main>
  );
}
