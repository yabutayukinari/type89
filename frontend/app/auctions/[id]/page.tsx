'use client';

import { use, useEffect, useState } from 'react';
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

export default function AuctionDetailPage({ params }: Props) {
  const { id } = use(params);
  const auctionId = Number(id);
  const { auth } = useUser();
  const [auction, setAuction] = useState<Auction | null>(null);
  const [bidAmount, setBidAmount] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [bids, setBids] = useState<Bid[]>([]);

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
    return <main className="p-8 text-sm">読み込み中...</main>;
  }

  // 終了時刻を過ぎたらサーバの status 更新を待たずに UI 上も終了扱いにする。
  const effectiveStatus =
    countdown.isOver && auction.status === 'active' ? 'ended' : auction.status;
  const isOwner = isAuthenticated && auth.principal.id === auction.seller.id;
  const canBid = isAuthenticated && !isOwner && effectiveStatus === 'active';

  return (
    <main className="mx-auto mt-12 flex w-full max-w-2xl flex-col gap-6 p-6">
      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold">{auction.title}</h1>
        <p className="text-sm text-zinc-600 dark:text-zinc-400">
          出品者: {auction.seller.name} / status: <strong>{effectiveStatus}</strong>
        </p>
      </header>

      <section className="flex items-center justify-between rounded-lg border border-zinc-300 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div>
          <p className="text-xs text-zinc-500">
            {effectiveStatus === 'pending' ? '開始まで' : '残り時間'}
          </p>
          <p
            data-testid="countdown"
            className={`text-lg font-semibold tabular-nums ${
              effectiveStatus === 'ended'
                ? 'text-zinc-500'
                : 'text-amber-600 dark:text-amber-400'
            }`}
          >
            {effectiveStatus === 'ended' ? '終了' : countdown.label}
          </p>
        </div>
        {viewerCount !== null && (
          <div className="text-right">
            <p className="text-xs text-zinc-500">観戦中</p>
            <p className="text-lg font-semibold tabular-nums">{viewerCount} 人</p>
          </div>
        )}
      </section>

      <section className="rounded-lg border border-zinc-300 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <p className="whitespace-pre-wrap text-sm">{auction.description}</p>
      </section>

      <section className="rounded-lg border border-zinc-300 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
          <dt className="text-zinc-500">開始価格</dt>
          <dd>{auction.starting_price.toLocaleString()} 円</dd>
          <dt className="text-zinc-500">現在価格</dt>
          <dd className="text-lg font-semibold text-emerald-600 dark:text-emerald-400">
            {auction.current_price.toLocaleString()} 円
          </dd>
          <dt className="text-zinc-500">最低次回入札</dt>
          <dd>{auction.min_next_bid.toLocaleString()} 円</dd>
          <dt className="text-zinc-500">最高入札者</dt>
          <dd>{auction.current_winner ? auction.current_winner.name : '—'}</dd>
          <dt className="text-zinc-500">開始</dt>
          <dd>{new Date(auction.starts_at).toLocaleString('ja-JP')}</dd>
          <dt className="text-zinc-500">終了</dt>
          <dd>{new Date(auction.ends_at).toLocaleString('ja-JP')}</dd>
        </dl>
      </section>

      <section className="rounded-lg border border-zinc-300 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <h2 className="mb-3 text-base font-semibold">入札</h2>
        {!isAuthenticated && (
          <p className="text-sm text-zinc-600 dark:text-zinc-400">
            入札にはログインが必要です
          </p>
        )}
        {isOwner && (
          <p className="text-sm text-zinc-600 dark:text-zinc-400">
            自分の出品物には入札できません
          </p>
        )}
        {isAuthenticated && !isOwner && effectiveStatus !== 'active' && (
          <p className="text-sm text-zinc-600 dark:text-zinc-400">
            現在は受付中ではありません
          </p>
        )}
        {canBid && (
          <div className="flex flex-col gap-2">
            <label className="flex flex-col gap-1 text-sm">
              入札額 (円)
              <input
                type="number"
                value={bidAmount}
                min={auction.min_next_bid}
                onChange={(e) => setBidAmount(e.target.value)}
                className="rounded border border-zinc-300 bg-white px-3 py-2 text-base dark:border-zinc-700 dark:bg-zinc-950"
              />
            </label>
            <button
              type="button"
              onClick={handleBid}
              disabled={submitting}
              className="self-start rounded bg-zinc-900 px-4 py-2 text-base font-medium text-white disabled:opacity-50 dark:bg-zinc-100 dark:text-zinc-900"
            >
              {submitting ? '送信中…' : '入札する'}
            </button>
            {error && (
              <p role="alert" className="text-sm text-red-600 dark:text-red-400">
                {error}
              </p>
            )}
          </div>
        )}
      </section>

      <section className="rounded-lg border border-zinc-300 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <h2 className="mb-3 text-base font-semibold">入札履歴 (リアルタイム)</h2>
        {bids.length === 0 ? (
          <p className="text-sm text-zinc-500">まだ入札はありません</p>
        ) : (
          <ol className="flex flex-col gap-1 text-sm">
            {bids.map((bid) => (
              <li key={bid.id} className="flex items-baseline justify-between gap-4">
                <span>
                  <strong>{bid.bidder.name}</strong> が {bid.amount.toLocaleString()} 円で入札
                </span>
                <span className="font-mono text-xs text-zinc-500">
                  {new Date(bid.created_at).toLocaleTimeString('ja-JP')}
                </span>
              </li>
            ))}
          </ol>
        )}
      </section>
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
