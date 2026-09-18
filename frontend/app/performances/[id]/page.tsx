'use client';

import Link from 'next/link';
import { use, useEffect, useRef, useState } from 'react';
import { extractApiMessage } from '@/lib/apiError';
import { useUser } from '@/lib/auth';
import { useCountdown } from '@/lib/countdown';
import { getEcho } from '@/lib/echo';
import {
  fetchPerformance,
  fetchQueueStatus,
  joinQueue,
  Performance,
  QueueAdmission,
  SeatsUpdatedPayload,
  SlotAssignedPayload,
} from '@/lib/performances';

type Props = { params: Promise<{ id: string }> };

const yen = (n: number): string => `¥${n.toLocaleString('ja-JP')}`;

const joinSteps = [
  { key: 'idle' as const, label: '未参加' },
  { key: 'waiting' as const, label: '待機中' },
  { key: 'secured' as const, label: '枠確保' },
];

export default function PerformanceDetailPage({ params }: Props) {
  const { id } = use(params);
  const performanceId = Number(id);
  const { auth } = useUser();
  const [performance, setPerformance] = useState<Performance | null>(null);
  const [admission, setAdmission] = useState<QueueAdmission | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [flash, setFlash] = useState(false);
  const prevRemaining = useRef<number | null>(null);

  const isAuthenticated = auth.state === 'authenticated';

  useEffect(() => {
    let cancelled = false;
    fetchPerformance(performanceId).then((data) => {
      if (!cancelled) setPerformance(data);
    });
    return () => {
      cancelled = true;
    };
  }, [performanceId]);

  useEffect(() => {
    if (!isAuthenticated) {
      return;
    }
    let cancelled = false;
    fetchQueueStatus(performanceId).then((data) => {
      if (!cancelled) {
        setAdmission(data);
        setPerformance(data.performance);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [performanceId, isAuthenticated]);

  useEffect(() => {
    const echo = getEcho();
    const channel = echo.channel(`performance.${performanceId}`);
    channel.listen('.seats.updated', (payload: SeatsUpdatedPayload) => {
      setPerformance((prev) =>
        prev
          ? { ...prev, remaining_seats: payload.remaining_seats, capacity: payload.capacity }
          : prev,
      );
      setAdmission((prev) =>
        prev
          ? {
              ...prev,
              performance: {
                ...prev.performance,
                remaining_seats: payload.remaining_seats,
                capacity: payload.capacity,
              },
            }
          : prev,
      );
    });
    return () => {
      echo.leave(`performance.${performanceId}`);
    };
  }, [performanceId]);

  useEffect(() => {
    if (auth.state !== 'authenticated') {
      return;
    }
    const userId = auth.principal.id;
    const echo = getEcho();
    const channelName = `user.${userId}`;
    echo.private(channelName).listen('.slot.assigned', (payload: SlotAssignedPayload) => {
      if (payload.purchase_slot.performance_id !== performanceId) {
        return;
      }
      setAdmission((prev) =>
        prev
          ? {
              ...prev,
              queue_entry: {
                id: payload.queue_entry.id,
                status: payload.queue_entry.status,
                position: payload.queue_entry.position,
                joined_at: prev.queue_entry?.joined_at ?? new Date().toISOString(),
              },
              purchase_slot: {
                id: payload.purchase_slot.id,
                assigned_at: payload.purchase_slot.assigned_at,
              },
            }
          : prev,
      );
    });
    return () => {
      echo.leave(channelName);
    };
  }, [auth, performanceId]);

  const visibleAdmission = isAuthenticated ? admission : null;
  const waitingWithoutSlot =
    visibleAdmission?.queue_entry !== null && visibleAdmission?.purchase_slot === null;
  useEffect(() => {
    if (!waitingWithoutSlot || !isAuthenticated) {
      return;
    }
    const timer = window.setInterval(() => {
      fetchQueueStatus(performanceId).then((data) => {
        setAdmission(data);
        setPerformance(data.performance);
      });
    }, 2000);
    return () => window.clearInterval(timer);
  }, [waitingWithoutSlot, isAuthenticated, performanceId]);

  useEffect(() => {
    const remaining = performance?.remaining_seats;
    if (remaining === undefined) {
      return;
    }
    if (prevRemaining.current !== null && prevRemaining.current !== remaining) {
      setFlash(true);
      prevRemaining.current = remaining;
      const timer = window.setTimeout(() => setFlash(false), 900);
      return () => window.clearTimeout(timer);
    }
    prevRemaining.current = remaining;
  }, [performance?.remaining_seats]);

  const handleJoin = async () => {
    setError(null);
    setSubmitting(true);
    try {
      const next = await joinQueue(performanceId);
      setAdmission(next);
      setPerformance(next.performance);
    } catch (err: unknown) {
      const message =
        err && typeof err === 'object' && 'response' in err
          ? extractApiMessage(err, '待機列に並べませんでした')
          : err instanceof Error
            ? err.message
            : '待機列に並べませんでした';
      setError(message);
    } finally {
      setSubmitting(false);
    }
  };

  const saleCountdown = useCountdown(performance?.sale_opens_at ?? new Date().toISOString());
  const closeCountdown = useCountdown(performance?.sale_closes_at ?? new Date().toISOString());

  if (!performance) {
    return (
      <main className="grid min-h-screen place-items-center bg-zinc-950 text-sm text-zinc-400">
        読み込み中...
      </main>
    );
  }

  const saleStatus =
    performance.sale_status === 'upcoming' && saleCountdown.isOver
      ? 'open'
      : performance.sale_status === 'open' && closeCountdown.isOver
        ? 'closed'
        : performance.sale_status;
  const isOpen = saleStatus === 'open';
  const soldOut = performance.remaining_seats === 0;
  const hasSlot = visibleAdmission?.purchase_slot !== null && visibleAdmission?.purchase_slot !== undefined;
  const inQueue = visibleAdmission?.queue_entry !== null && visibleAdmission?.queue_entry !== undefined;
  const joinState: 'idle' | 'waiting' | 'secured' = hasSlot ? 'secured' : inQueue ? 'waiting' : 'idle';
  const joinStateIndex = joinSteps.findIndex((step) => step.key === joinState);

  return (
    <main className="min-h-screen bg-zinc-950 text-zinc-100">
      <div className="mx-auto flex w-full max-w-2xl flex-col gap-5 p-6 pt-10">
        <div className="relative overflow-hidden rounded-2xl border border-zinc-800 bg-[radial-gradient(120%_90%_at_30%_0%,#1a1028,#0c0f14)] px-5 py-10">
          {isOpen && (
            <span className="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full border border-red-500/40 bg-red-500/15 px-2.5 py-1 text-[11px] font-extrabold tracking-wide text-red-300">
              {!soldOut && <span className="animate-live-pulse h-2 w-2 rounded-full bg-red-500" />}
              {soldOut ? '満席' : '販売中'}
            </span>
          )}
          <p className="text-xs font-semibold tracking-[0.2em] text-violet-300/80">{performance.show.venue_label}</p>
          <h1 className="mt-2 text-3xl font-black tracking-tight">{performance.show.title}</h1>
          <p className="mt-2 text-sm text-zinc-400">{new Date(performance.starts_at).toLocaleString('ja-JP')}</p>
        </div>

        <ol className="grid grid-cols-3 gap-2" aria-label="参加の状態">
          {joinSteps.map((step, i) => {
            const isCurrent = i === joinStateIndex;
            const isDone = i < joinStateIndex;
            return (
              <li
                key={step.key}
                aria-current={isCurrent ? 'step' : undefined}
                className={
                  isCurrent
                    ? 'rounded-xl border border-fuchsia-400/40 bg-fuchsia-500/10 px-3 py-2 text-center text-sm font-bold text-fuchsia-200'
                    : isDone
                      ? 'rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-center text-sm font-semibold text-zinc-300'
                      : 'rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-2 text-center text-sm text-zinc-500'
                }
              >
                {step.label}
              </li>
            );
          })}
        </ol>

        {hasSlot && (
          <div className="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5">
            <p className="text-4xl font-black tracking-tight text-emerald-300 md:text-5xl">枠取れた！</p>
            <p className="mt-2 text-sm text-zinc-200">このセッションの購入枠は1つだけです。決済は行いません。</p>
            <p className="mt-3 text-xs leading-relaxed text-zinc-500">
              デモはここまでです。別のブラウザで demo2@example.com にログインすると、同じ公演の残席が減る様子を確認できます。
            </p>
          </div>
        )}

        {joinState === 'waiting' ? (
          <div>
            <p className="text-xs font-medium tracking-wider text-zinc-500">自分の番</p>
            <p className="text-5xl font-black leading-none tracking-tight tabular-nums">
              {visibleAdmission?.queue_entry?.position}
              <span className="ml-2 text-lg font-semibold text-zinc-400">番目</span>
            </p>
            <p className="mt-3 text-sm text-zinc-500">
              全体の残席{' '}
              <span aria-live="polite" className={`font-semibold tabular-nums ${flash ? 'price-flash' : ''}`}>
                {performance.remaining_seats}
              </span>
              <span className="text-zinc-600"> / {performance.capacity}</span>
            </p>
          </div>
        ) : (
          <div>
            <p className="text-xs font-medium tracking-wider text-zinc-500">
              {hasSlot ? '参考の全体残席' : '残り席'}
            </p>
            <p className={`text-5xl font-black leading-none tracking-tight tabular-nums ${flash ? 'price-flash' : ''}`}>
              <span aria-live="polite">{performance.remaining_seats}</span>
              <span className="ml-2 text-lg font-semibold text-zinc-500">/ {performance.capacity}</span>
            </p>
          </div>
        )}

        <div className="grid grid-cols-2 gap-3">
          <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3">
            <p className="text-[11px] tracking-wide text-amber-300/80">
              {saleStatus === 'upcoming' ? '販売開始まで' : saleStatus === 'open' ? '販売終了まで' : '販売'}
            </p>
            <p className="text-2xl font-extrabold tabular-nums text-amber-300">
              {saleStatus === 'closed' ? '終了' : saleStatus === 'upcoming' ? saleCountdown.label : closeCountdown.label}
            </p>
          </div>
          <div className="rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3">
            <p className="text-[11px] tracking-wide text-zinc-500">チケット代金（デモ）</p>
            <p className="text-2xl font-extrabold tabular-nums text-zinc-100">{yen(performance.price)}</p>
          </div>
        </div>

        <section className="flex flex-col gap-3">
          {!hasSlot && inQueue && (
            <div className="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4">
              <p className="text-sm font-bold text-amber-300">
                {soldOut ? 'キャンセル待ちで待機中' : '待機中'}
              </p>
              <p className="mt-1 text-sm text-zinc-200">
                自分の番は {visibleAdmission?.queue_entry?.position} 番目です。
              </p>
              <p className="mt-1 text-sm text-zinc-400">
                {soldOut
                  ? 'いまは満席です。枠が開けば、並んだ順に割り当てられます。'
                  : '残席があれば枠はすぐに入ります。空席が開けば、その時点であなたに割り当てられます。'}
              </p>
            </div>
          )}

          {!isAuthenticated && isOpen && (
            <p className="rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-400">
              待機列に並ぶにはログインが必要です。{' '}
              <Link href={`/login?next=/performances/${performance.id}`} className="font-semibold text-amber-400">
                ログイン
              </Link>
            </p>
          )}

          {isAuthenticated && isOpen && !inQueue && (
            <>
              <button
                type="button"
                onClick={handleJoin}
                disabled={submitting}
                className="rounded-lg bg-gradient-to-r from-violet-400 to-fuchsia-500 px-4 py-3 text-base font-extrabold text-zinc-950 shadow-lg shadow-fuchsia-500/30 transition active:scale-[.99] disabled:opacity-50"
              >
                {submitting ? '枠を確認中…' : soldOut ? 'キャンセル待ちに並ぶ' : '待機列に並ぶ'}
              </button>
              {submitting && (
                <p className="text-sm text-zinc-400">
                  残席があれば、この場で枠が入ります。失敗ではありません。
                </p>
              )}
            </>
          )}

          {saleStatus === 'upcoming' && (
            <p className="rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-400">
              販売開始前です。開場と同時に待機列へ入れます。
            </p>
          )}
          {saleStatus === 'closed' && (
            <p className="rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-400">
              この公演の販売は終了しました
            </p>
          )}
          {error && (
            <p role="alert" className="text-sm text-red-400">
              {error}
            </p>
          )}
        </section>

        <section className="rounded-2xl border border-zinc-800 bg-zinc-900/60 p-4">
          <p className="whitespace-pre-wrap text-sm text-zinc-300">{performance.show.description}</p>
        </section>
      </div>
    </main>
  );
}
