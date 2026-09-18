'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { fetchPerformances, Performance } from '@/lib/performances';

const yen = (n: number): string => `¥${n.toLocaleString('ja-JP')}`;

const features = [
  {
    title: '開場と同時に待機列へ',
    body: '販売が開いたら待機列に並びます。同じ人・同じセッションに購入枠が二重に入ることはありません。',
    icon: (
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M4 6h16M4 12h10M4 18h7" /><circle cx="18" cy="15" r="3" /></svg>
    ),
  },
  {
    title: '公平な購入枠',
    body: '空席があるあいだ、並んだ順に1人1枠を割り当てます。取り合いでも同じ席を二人に渡しません。',
    icon: (
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3v18" /><path d="M5 8h14" /><path d="M7 16h10" /></svg>
    ),
  },
  {
    title: '残席がライブで減る',
    body: '誰かが枠を取ると、残席が Reverb 経由でその場で更新されます。ページをリロードする必要はありません。',
    icon: (
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7z" /></svg>
    ),
  },
];

const steps = [
  { n: '01', title: 'ログイン', body: 'デモは test_user@example.com / test1111。別ブラウザなら demo2@example.com でも並べます。' },
  { n: '02', title: '待機列に並ぶ', body: '販売中の公演を開き、待機列へ。空席があれば先着で購入枠が入ります。' },
  { n: '03', title: '残席を見る', body: '枠が埋まると残席がリアルタイムに減ります。決済は行わないデモです。' },
];

export default function Home() {
  const [performances, setPerformances] = useState<Performance[] | null>(null);

  useEffect(() => {
    let cancelled = false;
    fetchPerformances()
      .then((data) => {
        if (!cancelled) setPerformances(data);
      })
      .catch(() => {
        if (!cancelled) setPerformances([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const onSale = (performances ?? []).filter((p) => p.sale_status === 'open');
  const featured = onSale[0] ?? performances?.[0] ?? null;
  const liveCount = onSale.length;

  return (
    <main className="min-h-screen bg-zinc-950 text-zinc-100">
      <section className="relative overflow-hidden">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute -top-40 left-1/2 h-[420px] w-[820px] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(168,85,247,0.22),transparent)] blur-2xl"
        />
        <div className="relative mx-auto grid w-full max-w-5xl items-center gap-10 px-5 py-16 md:grid-cols-2 md:py-24">
          <div className="fade-up flex flex-col gap-5">
            <span className="inline-flex w-fit items-center gap-2 rounded-full border border-violet-400/30 bg-violet-500/10 px-3 py-1 text-xs font-bold tracking-wide text-violet-200">
              <span className="animate-live-pulse h-2 w-2 rounded-full bg-red-500" />
              フラッシュ販売デモ
            </span>
            <h1 className="text-4xl font-black leading-[1.1] tracking-tight text-white md:text-5xl">
              開場と同時に、
              <br />
              <span className="bg-gradient-to-r from-violet-300 via-fuchsia-400 to-amber-300 bg-clip-text text-transparent">
                公平に並ぶ。
              </span>
            </h1>
            <p className="max-w-md text-base leading-relaxed text-zinc-400">
              ミッドサイズのライブ・フェス・クラブ向けチケット販売のデモ。待機列に並び、1人1枠だけ購入枠を受け取り、残席はリアルタイムに減ります。
            </p>
            <div className="flex flex-wrap items-center gap-3 pt-1">
              <Link
                href={featured ? `/performances/${featured.id}` : '/performances'}
                className="rounded-xl bg-gradient-to-r from-violet-400 to-fuchsia-500 px-6 py-3 text-base font-extrabold text-zinc-950 shadow-lg shadow-fuchsia-500/30 transition active:scale-[.98]"
              >
                販売中の公演を見る
              </Link>
              <Link
                href="/login?next=/performances"
                className="rounded-xl border border-zinc-700 px-6 py-3 text-base font-semibold text-zinc-200 transition hover:bg-zinc-900"
              >
                ログイン
              </Link>
            </div>
            <p className="pt-1 text-sm text-zinc-500">
              {performances === null ? (
                '開催状況を読み込み中…'
              ) : liveCount > 0 ? (
                <>
                  <span className="font-bold text-emerald-400">{liveCount}件</span> が販売中 ・ 残席は Reverb でライブ更新
                </>
              ) : (
                'まもなく新しい公演の販売が始まります'
              )}
            </p>
          </div>

          <div className="fade-up md:justify-self-end" style={{ animationDelay: '120ms' }}>
            <FeaturedCard performance={featured} loading={performances === null} />
          </div>
        </div>
      </section>

      {onSale.length > 0 && (
        <section className="mx-auto w-full max-w-5xl px-5 py-10">
          <div className="mb-4 flex items-baseline justify-between">
            <h2 className="text-xl font-bold tracking-tight">販売中の公演</h2>
            <Link href="/performances" className="text-sm font-medium text-violet-300 hover:text-violet-200">
              すべて見る →
            </Link>
          </div>
          <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {onSale.slice(0, 6).map((p) => (
              <li key={p.id}>
                <Link
                  href={`/performances/${p.id}`}
                  className="group block h-full rounded-2xl border border-zinc-800 bg-zinc-900/60 p-4 transition duration-200 hover:-translate-y-1 hover:border-zinc-700 hover:bg-zinc-900"
                >
                  <p className="text-xs text-zinc-500">{p.show.venue_label}</p>
                  <div className="mt-1 flex items-center gap-2">
                    {p.remaining_seats > 0 && (
                      <span className="animate-live-pulse h-2 w-2 shrink-0 rounded-full bg-red-500" />
                    )}
                    <span className="truncate font-semibold">{p.show.title}</span>
                  </div>
                  <div className="mt-1 flex items-baseline justify-between gap-3">
                    <span className="text-lg font-black tabular-nums text-emerald-400">
                      残 {p.remaining_seats}/{p.capacity}
                    </span>
                    <span className="text-sm text-zinc-400">{yen(p.price)}</span>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        </section>
      )}

      <section className="mx-auto w-full max-w-5xl px-5 py-10">
        <h2 className="mb-6 text-center text-2xl font-bold tracking-tight">
          取り合いでも、<span className="text-violet-300">公平に</span>渡す
        </h2>
        <div className="grid gap-4 md:grid-cols-3">
          {features.map((f) => (
            <div key={f.title} className="rounded-2xl border border-zinc-800 bg-zinc-900/50 p-5">
              <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-violet-500/10 text-violet-300">
                <span className="h-6 w-6">{f.icon}</span>
              </div>
              <h3 className="mb-1.5 text-base font-bold">{f.title}</h3>
              <p className="text-sm leading-relaxed text-zinc-400">{f.body}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="mx-auto w-full max-w-5xl px-5 py-10">
        <h2 className="mb-6 text-center text-2xl font-bold tracking-tight">3ステップで参加</h2>
        <ol className="grid gap-4 md:grid-cols-3">
          {steps.map((s) => (
            <li key={s.n} className="rounded-2xl border border-zinc-800 bg-zinc-900/50 p-5">
              <div className="mb-2 font-mono text-sm font-bold text-violet-300">{s.n}</div>
              <h3 className="mb-1.5 text-base font-bold">{s.title}</h3>
              <p className="text-sm leading-relaxed text-zinc-400">{s.body}</p>
            </li>
          ))}
        </ol>
      </section>

      <section className="mx-auto w-full max-w-5xl px-5 pb-16 pt-6">
        <div className="relative overflow-hidden rounded-3xl border border-zinc-800 bg-zinc-900/60 px-6 py-12 text-center">
          <h2 className="relative text-2xl font-black tracking-tight md:text-3xl">開場に並んでみる？</h2>
          <p className="relative mx-auto mt-2 max-w-md text-sm text-zinc-400">
            デモ公演は席数が少ないので、複数ブラウザで並ぶと残席が減っていく様子を確認できます。
          </p>
          <div className="relative mt-6 flex justify-center">
            <Link
              href={featured ? `/performances/${featured.id}` : '/performances'}
              className="rounded-xl bg-gradient-to-r from-violet-400 to-fuchsia-500 px-7 py-3 text-base font-extrabold text-zinc-950 shadow-lg shadow-fuchsia-500/30 transition active:scale-[.98]"
            >
              デモ公演を開く
            </Link>
          </div>
        </div>
      </section>

      <footer className="border-t border-zinc-900 px-5 py-6">
        <div className="mx-auto flex w-full max-w-5xl items-center justify-between text-xs text-zinc-600">
          <span>type89 Tickets</span>
          <div className="flex gap-4">
            <Link href="/performances" className="hover:text-zinc-400">
              チケット
            </Link>
            <Link href="/login" className="hover:text-zinc-400">
              ログイン
            </Link>
          </div>
        </div>
      </footer>
    </main>
  );
}

function FeaturedCard({ performance, loading }: { performance: Performance | null; loading: boolean }) {
  return (
    <div className="w-full max-w-sm rounded-3xl border border-zinc-800 bg-zinc-900/70 p-4 shadow-2xl shadow-black/40">
      <div className="relative mb-4 flex aspect-[16/10] items-center justify-center overflow-hidden rounded-2xl border border-zinc-800 bg-[radial-gradient(120%_90%_at_30%_0%,#1a1028,#0c0f14)]">
        {performance?.sale_status === 'open' && (
          <span className="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full border border-red-500/40 bg-red-500/15 px-2.5 py-1 text-[11px] font-extrabold tracking-wide text-red-300">
            {performance.remaining_seats > 0 && (
              <span className="animate-live-pulse h-2 w-2 rounded-full bg-red-500" />
            )}
            {performance.remaining_seats === 0 ? '満席' : '販売中'}
          </span>
        )}
        <span className="text-sm font-semibold tracking-[0.2em] text-violet-200/80">LIVE SHOW</span>
      </div>
      {loading ? (
        <div className="space-y-3">
          <div className="h-4 w-2/3 rounded bg-zinc-800" />
          <div className="h-8 w-1/2 rounded bg-zinc-800" />
        </div>
      ) : performance ? (
        <>
          <p className="truncate text-sm text-zinc-400">{performance.show.title}</p>
          <div className="mt-1 flex items-end justify-between gap-3">
            <div>
              <p className="text-[11px] text-zinc-500">残り席</p>
              <p className="text-3xl font-black tabular-nums text-white">
                {performance.remaining_seats}
                <span className="ml-1 text-base font-semibold text-zinc-500">/ {performance.capacity}</span>
              </p>
            </div>
            <div className="text-right">
              <p className="text-[11px] text-zinc-500">料金</p>
              <p className="text-base font-bold">{yen(performance.price)}</p>
            </div>
          </div>
          <Link
            href={`/performances/${performance.id}`}
            className="mt-4 block rounded-xl bg-gradient-to-r from-violet-400 to-fuchsia-500 py-3 text-center text-sm font-extrabold text-zinc-950 transition active:scale-[.98]"
          >
            この公演の販売を見る
          </Link>
        </>
      ) : (
        <div className="py-6 text-center text-sm text-zinc-500">
          現在販売中の公演はありません
          <Link href="/performances" className="mt-3 block rounded-xl border border-zinc-700 py-2.5 font-semibold text-zinc-200 hover:bg-zinc-900">
            一覧を見る
          </Link>
        </div>
      )}
    </div>
  );
}
