'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { AuctionCountdown } from '@/components/AuctionCountdown';
import { Auction, fetchAuctions } from '@/lib/auctions';

const yen = (n: number): string => `¥${n.toLocaleString('ja-JP')}`;

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
    <main className="min-h-screen bg-zinc-950 text-zinc-100">
      <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-6 pt-10">
        <header className="flex items-center justify-between">
          <h1 className="text-2xl font-bold tracking-tight">オークション</h1>
          <Link
            href="/auctions/new"
            className="rounded-lg bg-gradient-to-r from-orange-400 to-red-500 px-4 py-2 text-sm font-extrabold text-zinc-950 shadow-lg shadow-red-500/25 transition active:scale-[.99]"
          >
            出品する
          </Link>
        </header>

        {error && (
          <p role="alert" className="text-sm text-red-400">
            {error}
          </p>
        )}

        {auctions === null && !error && (
          <p className="text-sm text-zinc-500">読み込み中...</p>
        )}

        {auctions !== null && auctions.length === 0 && (
          <p className="text-sm text-zinc-500">まだオークションはありません</p>
        )}

        <ul className="flex flex-col gap-3">
          {auctions?.map((a) => {
            const isActive = a.status === 'active';
            return (
              <li key={a.id}>
                <Link
                  href={`/auctions/${a.id}`}
                  className="block rounded-xl border border-zinc-800 bg-zinc-900/60 p-4 transition hover:border-zinc-700 hover:bg-zinc-900"
                >
                  <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        {isActive && (
                          <span className="animate-live-pulse h-2 w-2 shrink-0 rounded-full bg-red-500" />
                        )}
                        <span className="truncate font-semibold">{a.title}</span>
                      </div>
                      <p className="mt-1 truncate text-xs text-zinc-500">
                        出品者: {a.seller.name}
                      </p>
                    </div>
                    <div className="shrink-0 text-right">
                      <p className="text-lg font-black tabular-nums text-emerald-400">
                        {yen(a.current_price)}
                      </p>
                      <div className="mt-0.5">
                        <AuctionCountdown endsAt={a.ends_at} status={a.status} />
                      </div>
                    </div>
                  </div>
                </Link>
              </li>
            );
          })}
        </ul>
      </div>
    </main>
  );
}
