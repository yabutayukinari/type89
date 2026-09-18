'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { useAdmin } from '@/lib/auth';
import { fetchAdminPerformances, Performance } from '@/lib/performances';

export default function AdminPerformancesPage() {
  const router = useRouter();
  const { auth } = useAdmin();
  const [performances, setPerformances] = useState<Performance[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (auth.state === 'unauthenticated') {
      router.replace('/admin/login');
    }
  }, [auth.state, router]);

  useEffect(() => {
    if (auth.state !== 'authenticated') {
      return;
    }
    let cancelled = false;
    fetchAdminPerformances()
      .then((data) => {
        if (!cancelled) {
          setPerformances(data);
        }
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : '公演を取得できませんでした');
        }
      });
    return () => {
      cancelled = true;
    };
  }, [auth.state]);

  if (auth.state !== 'authenticated') {
    return (
      <main className="flex min-h-screen items-center justify-center p-8">
        <p className="text-zinc-600">読み込み中...</p>
      </main>
    );
  }

  return (
    <main className="mx-auto mt-12 flex w-full max-w-3xl flex-col gap-4 p-6">
      <p className="text-sm text-zinc-500">
        <Link href="/admin/me" className="font-medium text-violet-700">
          管理画面
        </Link>
        <span className="px-1">/</span>
        公演の在庫
      </p>
      <h1 className="text-2xl font-semibold">公演の在庫</h1>
      <p className="text-sm text-zinc-600">仮確保・確定・解放を会場スタッフが説明するための一覧です。</p>
      {error && (
        <p role="alert" className="text-sm text-red-600">
          {error}
        </p>
      )}
      <ul className="flex flex-col gap-3">
        {performances?.map((performance) => (
          <li key={performance.id}>
            <Link
              href={`/admin/performances/${performance.id}`}
              className="block rounded-xl border border-zinc-300 bg-white p-4 hover:border-zinc-400"
            >
              <p className="font-semibold">{performance.show.title}</p>
              <p className="mt-1 text-sm text-zinc-500">
                残 {performance.remaining_seats}/{performance.capacity} ・ 仮確保 TTL {Math.round(performance.hold_ttl_seconds / 60)} 分
              </p>
            </Link>
          </li>
        ))}
      </ul>
      {performances?.length === 0 && <p className="text-sm text-zinc-500">公演はまだありません</p>}
    </main>
  );
}
