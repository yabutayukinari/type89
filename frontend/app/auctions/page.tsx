'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { Auction, fetchAuctions } from '@/lib/auctions';

export default function AuctionsListPage() {
  const [auctions, setAuctions] = useState<Auction[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    fetchAuctions()
      .then((data) => {
        if (!cancelled) setAuctions(data);
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : 'failed to fetch');
        }
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <main className="mx-auto mt-12 flex w-full max-w-3xl flex-col gap-4 p-6">
      <header className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">オークション一覧</h1>
        <Link
          href="/auctions/new"
          className="rounded bg-zinc-900 px-3 py-2 text-sm font-medium text-white dark:bg-zinc-100 dark:text-zinc-900"
        >
          出品する
        </Link>
      </header>

      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {auctions === null && !error && (
        <p className="text-sm text-zinc-600 dark:text-zinc-400">読み込み中...</p>
      )}

      {auctions !== null && auctions.length === 0 && (
        <p className="text-sm text-zinc-600 dark:text-zinc-400">
          まだオークションはありません
        </p>
      )}

      <ul className="flex flex-col gap-3">
        {auctions?.map((a) => (
          <li
            key={a.id}
            className="rounded-lg border border-zinc-300 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"
          >
            <Link
              href={`/auctions/${a.id}`}
              className="flex items-baseline justify-between gap-4"
            >
              <span className="font-medium">{a.title}</span>
              <span className="text-sm text-zinc-500">
                {a.status === 'active' ? '現在価格' : a.status === 'ended' ? '終了' : '開始前'}{' '}
                <strong>{a.current_price.toLocaleString()}</strong> 円
              </span>
            </Link>
            <p className="mt-1 truncate text-xs text-zinc-500">出品者: {a.seller.name}</p>
          </li>
        ))}
      </ul>
    </main>
  );
}
