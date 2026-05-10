'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';

type HealthStatus =
  | { state: 'loading' }
  | { state: 'ok'; status: string }
  | { state: 'error'; message: string };

export default function Home() {
  const [health, setHealth] = useState<HealthStatus>({ state: 'loading' });

  useEffect(() => {
    const baseUrl = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost';

    fetch(`${baseUrl}/api/health`)
      .then(async (response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const data = (await response.json()) as { status?: string };
        setHealth({ state: 'ok', status: data.status ?? 'unknown' });
      })
      .catch((error: unknown) => {
        const message = error instanceof Error ? error.message : 'unknown error';
        setHealth({ state: 'error', message });
      });
  }, []);

  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-6 p-8">
      <h1 className="text-3xl font-bold">type89</h1>

      <section className="rounded-lg border border-zinc-300 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <h2 className="text-lg font-semibold">Laravel API health</h2>
        {health.state === 'loading' && (
          <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400">checking...</p>
        )}
        {health.state === 'ok' && (
          <p className="mt-2 text-sm text-emerald-600 dark:text-emerald-400">
            status: <strong>{health.status}</strong>
          </p>
        )}
        {health.state === 'error' && (
          <p className="mt-2 text-sm text-red-600 dark:text-red-400">{health.message}</p>
        )}
      </section>

      <nav className="flex flex-wrap justify-center gap-4 text-sm">
        <Link
          href="/auctions"
          className="rounded bg-zinc-900 px-4 py-2 text-white dark:bg-zinc-100 dark:text-zinc-900"
        >
          オークション一覧
        </Link>
        <Link
          href="/login"
          className="rounded border border-zinc-300 px-4 py-2 dark:border-zinc-700"
        >
          User ログイン
        </Link>
        <Link
          href="/admin/login"
          className="rounded border border-zinc-300 px-4 py-2 dark:border-zinc-700"
        >
          Admin ログイン
        </Link>
        <Link
          href="/broadcast-test"
          className="rounded border border-zinc-300 px-4 py-2 dark:border-zinc-700"
        >
          Broadcast 動作確認
        </Link>
      </nav>
    </main>
  );
}
