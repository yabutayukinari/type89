'use client';

import { use, useEffect, useRef, useState } from 'react';
import {
  Auction,
  Bid,
  BidPlacedPayload,
  fetchAuction,
  fetchBids,
  placeBid,
} from '@/lib/auctions';
import { useUser } from '@/lib/auth';
import { useCountdown } from '@/lib/countdown';
import { getEcho } from '@/lib/echo';
import { useViewerCount } from '@/lib/useViewerCount';

type Props = { params: Promise<{ id: string }> };

const yen = (n: number): string => `¥${n.toLocaleString('ja-JP')}`;

const CameraIcon = ({ className }: { className?: string }) => (
  <svg
    className={className}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="1.4"
    aria-hidden="true"
  >
    <path d="M3 8.5A1.5 1.5 0 0 1 4.5 7h2l1.2-1.8A1 1 0 0 1 8.5 4.8h7a1 1 0 0 1 .8.4L17.5 7h2A1.5 1.5 0 0 1 21 8.5v9A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5z" />
    <circle cx="12" cy="12.5" r="3.4" />
  </svg>
);

export default function AuctionDetailPage({ params }: Props) {
  const { id } = use(params);
  const auctionId = Number(id);
  const { auth } = useUser();
  const [auction, setAuction] = useState<Auction | null>(null);
  const [bidAmount, setBidAmount] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [bids, setBids] = useState<Bid[]>([]);
  const [flash, setFlash] = useState(false);
  const prevPrice = useRef<number | null>(null);

  useEffect(() => {
    let cancelled = false;
    Promise.all([fetchAuction(auctionId), fetchBids(auctionId)]).then(([a, history]) => {
      if (!cancelled) {
        setAuction(a);
        setBidAmount(String(a.min_next_bid));
        setBids(history);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [auctionId]);

  useEffect(() => {
    const echo = getEcho();
    const channel = echo.channel(`auction.${auctionId}`);

    channel.listen('.bid.placed', (payload: BidPlacedPayload) => {
      setAuction((prev) =>
        prev
          ? {
              ...prev,
              current_price: payload.auction.current_price,
              min_next_bid: payload.auction.min_next_bid,
              current_winner: payload.auction.current_winner,
            }
          : prev,
      );
      setBidAmount(String(payload.auction.min_next_bid));
      setBids((prev) => [payload.bid, ...prev]);
    });

    return () => {
      echo.leave(`auction.${auctionId}`);
    };
  }, [auctionId]);

  // 現在価格が変わった瞬間だけ、価格をグリーンにフラッシュさせる。
  useEffect(() => {
    const price = auction?.current_price;
    if (price === undefined) {
      return;
    }
    if (prevPrice.current !== null && prevPrice.current !== price) {
      setFlash(true);
      prevPrice.current = price;
      const timer = window.setTimeout(() => setFlash(false), 900);
      return () => window.clearTimeout(timer);
    }
    prevPrice.current = price;
  }, [auction?.current_price]);

  const handleBid = async () => {
    setError(null);
    setSubmitting(true);
    try {
      await placeBid(auctionId, Number(bidAmount));
    } catch (err: unknown) {
      const message =
        err && typeof err === 'object' && 'response' in err
          ? extractMessage(err)
          : err instanceof Error
            ? err.message
            : 'bid failed';
      setError(message);
    } finally {
      setSubmitting(false);
    }
  };

  const isAuthenticated = auth.state === 'authenticated';
  const viewerCount = useViewerCount(auctionId, isAuthenticated);
  const countdown = useCountdown(auction?.ends_at ?? new Date().toISOString());

  if (!auction) {
    return (
      <main className="grid min-h-screen place-items-center bg-zinc-950 text-sm text-zinc-400">
        読み込み中...
      </main>
    );
  }

  // 終了時刻を過ぎたらサーバの status 更新を待たずに UI 上も終了扱いにする。
  const effectiveStatus =
    countdown.isOver && auction.status === 'active' ? 'ended' : auction.status;
  const isActive = effectiveStatus === 'active';
  const isOwner = isAuthenticated && auth.principal.id === auction.seller.id;
  const canBid = isAuthenticated && !isOwner && isActive;

  return (
    <main className="min-h-screen bg-zinc-950 text-zinc-100">
      <div className="mx-auto flex w-full max-w-2xl flex-col gap-5 p-6 pt-10">
        {/* 商品ビジュアル (画像未対応のためプレースホルダ) */}
        <div className="relative flex aspect-[16/10] items-center justify-center overflow-hidden rounded-2xl border border-zinc-800 bg-[radial-gradient(120%_90%_at_30%_0%,#23180a,#0c0f14)]">
          {isActive && (
            <span className="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full border border-red-500/40 bg-red-500/15 px-2.5 py-1 text-[11px] font-extrabold tracking-wide text-red-300">
              <span className="animate-live-pulse h-2 w-2 rounded-full bg-red-500" />
              LIVE
            </span>
          )}
          {viewerCount !== null && (
            <span className="absolute right-3 top-3 rounded-full bg-black/40 px-2.5 py-1 text-[11px] font-semibold text-zinc-200">
              👁 {viewerCount}人が観戦中
            </span>
          )}
          <CameraIcon className="h-16 w-16 text-amber-400/90" />
        </div>

        {/* タイトル */}
        <div>
          <h1 className="text-2xl font-bold tracking-tight">{auction.title}</h1>
          <p className="mt-1 text-sm text-zinc-400">
            出品者: {auction.seller.name} ・{' '}
            <span
              className={
                isActive ? 'text-emerald-400' : effectiveStatus === 'pending' ? 'text-sky-400' : 'text-zinc-500'
              }
            >
              {isActive ? '開催中' : effectiveStatus === 'pending' ? '開始前' : '終了'}
            </span>
          </p>
        </div>

        {/* 現在価格 (ヒーロー) */}
        <div>
          <p className="text-xs font-medium tracking-wider text-zinc-500">現在価格</p>
          <p
            className={`text-5xl font-black leading-none tracking-tight tabular-nums ${flash ? 'price-flash' : ''}`}
          >
            {yen(auction.current_price)}
          </p>
        </div>

        {/* 残り時間 + 最低次回入札 */}
        <div className="grid grid-cols-2 gap-3">
          <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3">
            <p className="text-[11px] tracking-wide text-amber-300/80">
              {effectiveStatus === 'pending' ? '開始まで' : '残り時間'}
            </p>
            <p className="text-2xl font-extrabold tabular-nums text-amber-300">
              {effectiveStatus === 'ended' ? '終了' : countdown.label}
            </p>
          </div>
          <div className="rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3">
            <p className="text-[11px] tracking-wide text-zinc-500">最低次回入札</p>
            <p className="text-2xl font-extrabold tabular-nums text-zinc-100">
              {yen(auction.min_next_bid)}
            </p>
          </div>
        </div>

        {/* 入札 */}
        <section className="flex flex-col gap-3">
          {!isAuthenticated && (
            <p className="rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-400">
              入札にはログインが必要です
            </p>
          )}
          {isOwner && (
            <p className="rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-400">
              自分の出品物には入札できません
            </p>
          )}
          {isAuthenticated && !isOwner && !isActive && (
            <p className="rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-400">
              現在は受付中ではありません
            </p>
          )}
          {canBid && (
            <>
              <label className="flex flex-col gap-1 text-sm text-zinc-400">
                入札額 (円)
                <input
                  type="number"
                  value={bidAmount}
                  min={auction.min_next_bid}
                  onChange={(e) => setBidAmount(e.target.value)}
                  className="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2.5 text-base tabular-nums text-zinc-100 focus:border-amber-500 focus:outline-none"
                />
              </label>
              <button
                type="button"
                onClick={handleBid}
                disabled={submitting}
                className="rounded-lg bg-gradient-to-r from-orange-400 to-red-500 px-4 py-3 text-base font-extrabold text-zinc-950 shadow-lg shadow-red-500/30 transition active:scale-[.99] disabled:opacity-50"
              >
                {submitting ? '送信中…' : '⚡ すぐ入札する'}
              </button>
              {error && (
                <p role="alert" className="text-sm text-red-400">
                  {error}
                </p>
              )}
            </>
          )}
        </section>

        {/* 説明 */}
        <section className="rounded-2xl border border-zinc-800 bg-zinc-900/60 p-4">
          <p className="whitespace-pre-wrap text-sm text-zinc-300">{auction.description}</p>
        </section>

        {/* 詳細情報 */}
        <section className="rounded-2xl border border-zinc-800 bg-zinc-900/60 p-4">
          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
            <dt className="text-zinc-500">開始価格</dt>
            <dd className="tabular-nums">{yen(auction.starting_price)}</dd>
            <dt className="text-zinc-500">最高入札者</dt>
            <dd>{auction.current_winner ? auction.current_winner.name : '—'}</dd>
            <dt className="text-zinc-500">開始</dt>
            <dd className="tabular-nums">{new Date(auction.starts_at).toLocaleString('ja-JP')}</dd>
            <dt className="text-zinc-500">終了</dt>
            <dd className="tabular-nums">{new Date(auction.ends_at).toLocaleString('ja-JP')}</dd>
          </dl>
        </section>

        {/* 入札履歴 (リアルタイム) */}
        <section className="rounded-2xl border border-zinc-800 bg-zinc-900/60 p-4">
          <h2 className="mb-3 text-base font-semibold">入札履歴 (リアルタイム)</h2>
          {bids.length === 0 ? (
            <p className="text-sm text-zinc-500">まだ入札はありません</p>
          ) : (
            <ol className="flex flex-col gap-1.5 text-sm">
              {bids.map((bid, index) => (
                <li
                  key={bid.id}
                  className={`flex items-baseline justify-between gap-4 rounded-lg px-2 py-1 ${
                    index === 0 ? 'bg-emerald-500/10 text-emerald-300' : 'text-zinc-300'
                  }`}
                >
                  <span>
                    {index === 0 && '🔥 '}
                    <strong>{bid.bidder.name}</strong> が入札
                  </span>
                  <span className="flex items-baseline gap-3">
                    <span className="font-bold tabular-nums">{yen(bid.amount)}</span>
                    <span className="font-mono text-xs text-zinc-500">
                      {new Date(bid.created_at).toLocaleTimeString('ja-JP')}
                    </span>
                  </span>
                </li>
              ))}
            </ol>
          )}
        </section>
      </div>
    </main>
  );
}

const extractMessage = (err: { response?: unknown }): string => {
  const response = err.response;
  if (
    response &&
    typeof response === 'object' &&
    'data' in response &&
    response.data &&
    typeof response.data === 'object' &&
    'message' in response.data &&
    typeof response.data.message === 'string'
  ) {
    return response.data.message;
  }
  return 'bid failed';
};
