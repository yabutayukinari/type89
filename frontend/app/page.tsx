'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { AuctionCountdown } from '@/components/AuctionCountdown';
import { Auction, fetchAuctions } from '@/lib/auctions';

const yen = (n: number): string => `¥${n.toLocaleString('ja-JP')}`;

const CameraIcon = ({ className }: { className?: string }) => (
  <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" aria-hidden="true">
    <path d="M3 8.5A1.5 1.5 0 0 1 4.5 7h2l1.2-1.8A1 1 0 0 1 8.5 4.8h7a1 1 0 0 1 .8.4L17.5 7h2A1.5 1.5 0 0 1 21 8.5v9A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5z" />
    <circle cx="12" cy="12.5" r="3.4" />
  </svg>
);

const features = [
  {
    title: 'リアルタイム入札',
    body: '現在価格も残り時間も秒単位で更新。ページを更新しなくても、他の入札がその場で反映されます。',
    icon: (
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7z" /></svg>
    ),
  },
  {
    title: '落札をすぐ通知',
    body: 'オークション終了と同時に落札結果を判定。あなたが落札したか、その場で通知が届きます。',
    icon: (
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.7 21a2 2 0 0 1-3.4 0" /></svg>
    ),
  },
  {
    title: '観戦人数を可視化',
    body: '今このオークションを何人が見ているかをリアルタイム表示。競り合いの熱気がそのまま伝わります。',
    icon: (
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" /><circle cx="12" cy="12" r="3" /></svg>
    ),
  },
];

const steps = [
  { n: '01', title: 'ログイン', body: 'メールアドレスでサインイン。デモは bidder@example.com / password ですぐ試せます。' },
  { n: '02', title: '入札する', body: '気になる出品を開いて、ワンタップで入札。価格と最低次回入札は自動で更新。' },
  { n: '03', title: '落札・通知', body: '終了時刻に最高額なら落札。結果はマイページとリアルタイム通知で受け取れます。' },
];

export default function Home() {
  const [auctions, setAuctions] = useState<Auction[] | null>(null);

  useEffect(() => {
    let cancelled = false;
    fetchAuctions()
      .then((data) => {
        if (!cancelled) setAuctions(data);
      })
      .catch(() => {
        if (!cancelled) setAuctions([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const live = (auctions ?? []).filter((a) => a.status === 'active');
  const featured = live[0] ?? null;
  const liveCount = live.length;

  return (
    <main className="min-h-screen bg-zinc-950 text-zinc-100">
      {/* HERO */}
      <section className="relative overflow-hidden">
        {/* 背景のグロー */}
        <div
          aria-hidden="true"
          className="pointer-events-none absolute -top-40 left-1/2 h-[420px] w-[820px] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(255,77,61,0.22),transparent)] blur-2xl"
        />
        <div className="relative mx-auto grid w-full max-w-5xl items-center gap-10 px-5 py-16 md:grid-cols-2 md:py-24">
          {/* コピー */}
          <div className="fade-up flex flex-col gap-5">
            <span className="inline-flex w-fit items-center gap-2 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-bold tracking-wide text-amber-300">
              <span className="animate-live-pulse h-2 w-2 rounded-full bg-red-500" />
              リアルタイム・オークション
            </span>
            <h1 className="text-4xl font-black leading-[1.1] tracking-tight text-white md:text-5xl">
              その一瞬に、
              <br />
              <span className="bg-gradient-to-r from-amber-300 via-orange-400 to-red-500 bg-clip-text text-transparent">
                競り落とす。
              </span>
            </h1>
            <p className="max-w-md text-base leading-relaxed text-zinc-400">
              残り時間、現在価格、他の入札者の動き ― すべてが秒単位で動くライブなオークション。ワンタップで入札し、落札はその場で通知が届きます。
            </p>
            <div className="flex flex-wrap items-center gap-3 pt-1">
              <Link
                href="/auctions"
                className="rounded-xl bg-gradient-to-r from-orange-400 to-red-500 px-6 py-3 text-base font-extrabold text-zinc-950 shadow-lg shadow-red-500/30 transition active:scale-[.98]"
              >
                オークションを見る
              </Link>
              <Link
                href="/login"
                className="rounded-xl border border-zinc-700 px-6 py-3 text-base font-semibold text-zinc-200 transition hover:bg-zinc-900"
              >
                ログイン
              </Link>
            </div>
            <p className="pt-1 text-sm text-zinc-500">
              {auctions === null ? (
                '開催状況を読み込み中…'
              ) : liveCount > 0 ? (
                <>
                  <span className="font-bold text-emerald-400">{liveCount}件</span> が今まさに開催中 ・ 観戦人数もリアルタイム表示
                </>
              ) : (
                'まもなく新しいオークションが開催されます'
              )}
            </p>
          </div>

          {/* ライブ・プレビューカード（実データ） */}
          <div className="fade-up md:justify-self-end" style={{ animationDelay: '120ms' }}>
            <FeaturedCard auction={featured} loading={auctions === null} />
          </div>
        </div>
      </section>

      {/* 開催中のオークション */}
      {live.length > 0 && (
        <section className="mx-auto w-full max-w-5xl px-5 py-10">
          <div className="mb-4 flex items-baseline justify-between">
            <h2 className="text-xl font-bold tracking-tight">開催中のオークション</h2>
            <Link href="/auctions" className="text-sm font-medium text-amber-400 hover:text-amber-300">
              すべて見る →
            </Link>
          </div>
          <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {live.slice(0, 6).map((a) => (
              <li key={a.id}>
                <Link
                  href={`/auctions/${a.id}`}
                  className="group block h-full rounded-2xl border border-zinc-800 bg-zinc-900/60 p-4 transition duration-200 hover:-translate-y-1 hover:border-zinc-700 hover:bg-zinc-900"
                >
                  <div className="mb-3 flex aspect-[16/9] items-center justify-center rounded-xl border border-zinc-800 bg-[radial-gradient(120%_90%_at_30%_0%,#23180a,#0c0f14)]">
                    <CameraIcon className="h-9 w-9 text-amber-400/80" />
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="animate-live-pulse h-2 w-2 shrink-0 rounded-full bg-red-500" />
                    <span className="truncate font-semibold">{a.title}</span>
                  </div>
                  <div className="mt-1 flex items-baseline justify-between gap-3">
                    <span className="text-lg font-black tabular-nums text-emerald-400">{yen(a.current_price)}</span>
                    <AuctionCountdown endsAt={a.ends_at} status={a.status} />
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        </section>
      )}

      {/* 特徴 */}
      <section className="mx-auto w-full max-w-5xl px-5 py-10">
        <h2 className="mb-6 text-center text-2xl font-bold tracking-tight">
          ただの通販じゃない、<span className="text-amber-400">ライブな</span>体験
        </h2>
        <div className="grid gap-4 md:grid-cols-3">
          {features.map((f) => (
            <div key={f.title} className="rounded-2xl border border-zinc-800 bg-zinc-900/50 p-5">
              <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-amber-500/10 text-amber-400">
                <span className="h-6 w-6">{f.icon}</span>
              </div>
              <h3 className="mb-1.5 text-base font-bold">{f.title}</h3>
              <p className="text-sm leading-relaxed text-zinc-400">{f.body}</p>
            </div>
          ))}
        </div>
      </section>

      {/* 使い方 */}
      <section className="mx-auto w-full max-w-5xl px-5 py-10">
        <h2 className="mb-6 text-center text-2xl font-bold tracking-tight">3ステップで参加</h2>
        <ol className="grid gap-4 md:grid-cols-3">
          {steps.map((s) => (
            <li key={s.n} className="rounded-2xl border border-zinc-800 bg-zinc-900/50 p-5">
              <div className="mb-2 font-mono text-sm font-bold text-amber-400">{s.n}</div>
              <h3 className="mb-1.5 text-base font-bold">{s.title}</h3>
              <p className="text-sm leading-relaxed text-zinc-400">{s.body}</p>
            </li>
          ))}
        </ol>
      </section>

      {/* 締めのCTA */}
      <section className="mx-auto w-full max-w-5xl px-5 pb-16 pt-6">
        <div className="relative overflow-hidden rounded-3xl border border-zinc-800 bg-zinc-900/60 px-6 py-12 text-center">
          <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-x-0 -top-24 mx-auto h-56 w-[560px] rounded-full bg-[radial-gradient(closest-side,rgba(255,138,61,0.20),transparent)] blur-2xl"
          />
          <h2 className="relative text-2xl font-black tracking-tight md:text-3xl">競りに参加してみる？</h2>
          <p className="relative mx-auto mt-2 max-w-md text-sm text-zinc-400">
            開催中のオークションをのぞいて、気になる品にワンタップで入札。
          </p>
          <div className="relative mt-6 flex justify-center">
            <Link
              href="/auctions"
              className="rounded-xl bg-gradient-to-r from-orange-400 to-red-500 px-7 py-3 text-base font-extrabold text-zinc-950 shadow-lg shadow-red-500/30 transition active:scale-[.98]"
            >
              オークションを見る
            </Link>
          </div>
        </div>
      </section>

      <footer className="border-t border-zinc-900 px-5 py-6">
        <div className="mx-auto flex w-full max-w-5xl items-center justify-between text-xs text-zinc-600">
          <span>⚡ type89 Auctions</span>
          <div className="flex gap-4">
            <Link href="/auctions" className="hover:text-zinc-400">
              オークション
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

function FeaturedCard({ auction, loading }: { auction: Auction | null; loading: boolean }) {
  return (
    <div className="w-full max-w-sm rounded-3xl border border-zinc-800 bg-zinc-900/70 p-4 shadow-2xl shadow-black/40">
      <div className="relative mb-4 flex aspect-[16/10] items-center justify-center overflow-hidden rounded-2xl border border-zinc-800 bg-[radial-gradient(120%_90%_at_30%_0%,#23180a,#0c0f14)]">
        {auction && (
          <span className="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full border border-red-500/40 bg-red-500/15 px-2.5 py-1 text-[11px] font-extrabold tracking-wide text-red-300">
            <span className="animate-live-pulse h-2 w-2 rounded-full bg-red-500" />
            LIVE
          </span>
        )}
        <CameraIcon className="h-14 w-14 text-amber-400/90" />
      </div>
      {loading ? (
        <div className="space-y-3">
          <div className="h-4 w-2/3 rounded bg-zinc-800" />
          <div className="h-8 w-1/2 rounded bg-zinc-800" />
        </div>
      ) : auction ? (
        <>
          <p className="truncate text-sm text-zinc-400">{auction.title}</p>
          <div className="mt-1 flex items-end justify-between gap-3">
            <div>
              <p className="text-[11px] text-zinc-500">現在価格</p>
              <p className="text-3xl font-black tabular-nums text-white">{yen(auction.current_price)}</p>
            </div>
            <div className="text-right">
              <p className="text-[11px] text-zinc-500">残り時間</p>
              <div className="text-base font-bold">
                <AuctionCountdown endsAt={auction.ends_at} status={auction.status} />
              </div>
            </div>
          </div>
          <Link
            href={`/auctions/${auction.id}`}
            className="mt-4 block rounded-xl bg-gradient-to-r from-orange-400 to-red-500 py-3 text-center text-sm font-extrabold text-zinc-950 transition active:scale-[.98]"
          >
            このオークションを見る
          </Link>
        </>
      ) : (
        <div className="py-6 text-center text-sm text-zinc-500">
          現在開催中のオークションはありません
          <Link href="/auctions" className="mt-3 block rounded-xl border border-zinc-700 py-2.5 font-semibold text-zinc-200 hover:bg-zinc-900">
            一覧を見る
          </Link>
        </div>
      )}
    </div>
  );
}
