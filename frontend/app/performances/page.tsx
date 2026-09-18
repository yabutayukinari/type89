'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { fetchPerformances, Performance } from '@/lib/performances';

const yen = (n: number): string => `¥${n.toLocaleString('ja-JP')}`;

const saleLabel = (status: Performance['sale_status']): string => {
  if (status === 'open') return '販売中';
  if (status === 'upcoming') return 'まもなく開場';
  return '販売終了';
};

export default function PerformancesListPage() {
  const [performances, setPerformances] = useState<Performance[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    fetchPerformances()
      .then((data) => {
        if (!cancelled) setPerformances(data);
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
        <header>
          <h1 className="text-2xl font-bold tracking-tight">公演チケット</h1>
          <p className="mt-1 text-sm text-zinc-500">フラッシュ販売のデモ。並んで、1人1枠まで。決済は行いません。</p>
        </header>

        {error && (
          <p role="alert" className="text-sm text-red-400">
            {error}
          </p>
        )}

        {performances === null && !error && <p className="text-sm text-zinc-500">読み込み中...</p>}

        {performances !== null && performances.length === 0 && (
          <p className="text-sm text-zinc-500">販売中の公演はまだありません</p>
        )}

        <ul className="flex flex-col gap-3">
          {performances?.map((p) => {
            const isOpen = p.sale_status === 'open';
            const soldOut = p.remaining_seats === 0;
            return (
              <li key={p.id}>
                <Link
                  href={`/performances/${p.id}`}
                  className="block rounded-xl border border-zinc-800 bg-zinc-900/60 p-4 transition hover:border-zinc-700 hover:bg-zinc-900"
                >
                  <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        {isOpen && !soldOut && (
                          <span className="animate-live-pulse h-2 w-2 shrink-0 rounded-full bg-red-500" />
                        )}
                        <span className="truncate font-semibold">{p.show.title}</span>
                      </div>
                      <p className="mt-1 truncate text-xs text-zinc-500">{p.show.venue_label}</p>
                    </div>
                    <div className="shrink-0 text-right">
                      <p className="text-lg font-black tabular-nums text-emerald-400">
                        残 {p.remaining_seats}/{p.capacity}
                      </p>
                      <p className="mt-0.5 text-xs text-zinc-500">
                        {soldOut && isOpen ? '満席' : saleLabel(p.sale_status)} ・ {yen(p.price)}
                      </p>
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
