'use client';

import Link from 'next/link';
import { use, useMemo, useState } from 'react';
import { useUser } from '@/lib/auth';
import { useCountdown } from '@/lib/countdown';
import { connectionCopy, holdTtlCopy, slotReleaseCopy, waitReasonCopy } from '@/lib/ticketQueueTrust';
import { useTicketQueue } from '@/lib/useTicketQueue';

type Props = { params: Promise<{ id: string }> };

const yen = (n: number): string => `¥${n.toLocaleString('ja-JP')}`;

const joinSteps = [
  { key: 'idle' as const, label: '未参加' },
  { key: 'waiting' as const, label: '待機中' },
  { key: 'held' as const, label: '仮確保' },
  { key: 'confirmed' as const, label: '確定' },
];

const relativeTime = (timestamp: number | null): string => {
  if (timestamp === null) {
    return '未確認';
  }
  const delta = Math.max(0, Math.round((Date.now() - timestamp) / 1000));
  if (delta < 3) {
    return 'たった今';
  }
  if (delta < 60) {
    return `${delta}秒前`;
  }
  return `${Math.round(delta / 60)}分前`;
};

export default function PerformanceDetailPage({ params }: Props) {
  const { id } = use(params);
  const performanceId = Number(id);
  const { auth } = useUser();
  const queue = useTicketQueue(performanceId, auth);
  const {
    performance,
    admission,
    error,
    submitting,
    confirming,
    cancelling,
    ready,
    flash,
    notices,
    connectionState,
    inventoryStale,
    lastSyncedAt,
    handleJoin,
    handleConfirm,
    handleCancel,
  } = queue;
  const [cancelOpen, setCancelOpen] = useState(false);

  const saleCountdown = useCountdown(performance?.sale_opens_at ?? new Date().toISOString());
  const closeCountdown = useCountdown(performance?.sale_closes_at ?? new Date().toISOString());
  const holdCountdown = useCountdown(admission?.purchase_slot?.expires_at ?? new Date().toISOString());

  const isAuthenticated = auth.state === 'authenticated';
  const slot = admission?.purchase_slot ?? null;
  const hasSlot = slot != null;
  const isHeld = slot?.status === 'held';
  const isConfirmed = slot?.status === 'confirmed';
  const entryStatus = admission?.queue_entry?.status;
  const released = entryStatus === 'cancelled' || entryStatus === 'expired';
  const inQueue = admission?.queue_entry != null && !released;
  const joinState: (typeof joinSteps)[number]['key'] = isConfirmed
    ? 'confirmed'
    : isHeld
      ? 'held'
      : inQueue
        ? 'waiting'
        : 'idle';
  const joinDisabled = submitting || joinState !== 'idle';
  const ttlSeconds = admission?.hold_ttl_seconds ?? performance?.hold_ttl_seconds ?? 180;
  const releaseCopy = slotReleaseCopy(admission?.slot_release ?? null, entryStatus);

  const waitCopy = useMemo(
    () => waitReasonCopy(admission?.wait_reason ?? null, admission?.admitted_count ?? 0, admission?.waiting_ahead ?? 0),
    [admission?.admitted_count, admission?.wait_reason, admission?.waiting_ahead],
  );

  if (!ready || !performance) {
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

        <ol className="grid grid-cols-4 gap-2" aria-label="参加の状態">
          {joinSteps.map((step, i) => {
            const isCurrent = i === joinStateIndex;
            const isDone = i < joinStateIndex;
            return (
              <li
                key={step.key}
                aria-current={isCurrent ? 'step' : undefined}
                className={
                  isCurrent
                    ? 'rounded-xl border border-fuchsia-400/40 bg-fuchsia-500/10 px-2 py-2 text-center text-xs font-bold text-fuchsia-200 sm:text-sm'
                    : isDone
                      ? 'rounded-xl border border-zinc-700 bg-zinc-900 px-2 py-2 text-center text-xs font-semibold text-zinc-300 sm:text-sm'
                      : 'rounded-xl border border-zinc-800 bg-zinc-950 px-2 py-2 text-center text-xs text-zinc-500 sm:text-sm'
                }
              >
                {step.label}
              </li>
            );
          })}
        </ol>

        {isConfirmed && (
          <div className="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5">
            <p className="text-4xl font-black tracking-tight text-emerald-300 md:text-5xl">購入確定</p>
            <p className="mt-2 text-sm text-zinc-200">このアカウントの枠は確定済みです。決済はありません。期限切れにも、ここからのキャンセルにもなりません。</p>
            {soldOut ? (
              <p className="mt-3 text-sm leading-relaxed text-emerald-100/90">
                全体の残り席は0（満席）ですが、それは他の人向けの数字です。あなたの確定枠はすでに確保されており、全体残席には含まれません。
              </p>
            ) : (
              <p className="mt-3 text-sm leading-relaxed text-zinc-400">
                下の残り席は全体の在庫です。あなたの確定枠は別カウントなので、残席が動いても取り消されません。
              </p>
            )}
          </div>
        )}

        {isHeld && (
          <div className="rounded-2xl border border-amber-400/40 bg-amber-500/10 p-5">
            <p className="text-4xl font-black tracking-tight text-amber-200 md:text-5xl">仮確保中</p>
            <p className="mt-2 text-sm text-zinc-200">枠は今このアカウントに入っています。まだ確定ではないので、期限までに「確定」してください。</p>
            <p className="mt-3 text-xs font-medium tracking-wider text-amber-200/80">確定までの残り</p>
            <p className="text-3xl font-black tabular-nums text-amber-100">{holdCountdown.isOver ? '期限切れ' : holdCountdown.label}</p>
            <p className="mt-3 text-sm leading-relaxed text-zinc-300">{holdTtlCopy(ttlSeconds)}</p>
          </div>
        )}

        {released && !hasSlot && releaseCopy && (
          <div className="rounded-2xl border border-sky-500/30 bg-sky-500/10 p-5">
            <p className="text-2xl font-black tracking-tight text-sky-100">{releaseCopy.title}</p>
            <p className="mt-2 text-sm leading-relaxed text-zinc-200">{releaseCopy.body}</p>
            <p className="mt-3 text-sm text-zinc-400">もう一度並ぶ場合は、待機列の後ろに入ります。抜けたままにはなりません。</p>
          </div>
        )}

        {joinState === 'waiting' ? (
          <div>
            <p className="text-xs font-medium tracking-wider text-zinc-500">並び順（参加時点で確定）</p>
            <p className="text-5xl font-black leading-none tracking-tight tabular-nums">
              {admission?.queue_entry?.position}
              <span className="ml-2 text-lg font-semibold text-zinc-400">番目</span>
            </p>
            <p className="mt-2 text-sm text-zinc-500">この番号は減りません。進み具合は前の待機人数と残席で分かります。</p>
            <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
              <div className="rounded-xl border border-zinc-800 bg-zinc-900 px-3 py-2">
                <dt className="text-[11px] text-zinc-500">前の待機</dt>
                <dd className="text-lg font-bold tabular-nums">{admission?.waiting_ahead ?? 0}人</dd>
              </div>
              <div className="rounded-xl border border-zinc-800 bg-zinc-900 px-3 py-2">
                <dt className="text-[11px] text-zinc-500">枠を確保した人</dt>
                <dd className="text-lg font-bold tabular-nums">{admission?.admitted_count ?? 0}人</dd>
              </div>
            </dl>
            <p className="mt-3 text-sm text-zinc-500">
              他の人向けの残席{' '}
              <span aria-live="polite" className={`font-semibold tabular-nums ${flash ? 'price-flash' : ''}`}>
                {performance.remaining_seats}
              </span>
              <span className="text-zinc-600"> / {performance.capacity}</span>
              {inventoryStale ? '（確認中）' : ''}
            </p>
          </div>
        ) : hasSlot ? (
          <div>
            <p className="text-xs font-medium tracking-wider text-zinc-500">他の人向けの残り席</p>
            <p className={`text-3xl font-black leading-none tracking-tight tabular-nums ${flash ? 'price-flash' : ''}`}>
              <span aria-live="polite">{performance.remaining_seats}</span>
              <span className="ml-2 text-base font-semibold text-zinc-500">/ {performance.capacity}</span>
            </p>
            <p className="mt-2 text-xs text-zinc-500">
              {soldOut
                ? '全体は満席です。あなたの確保済み枠とは別です。'
                : 'この数字は全体在庫です。あなたの枠は確保済みなのでここから消えません。'}
            </p>
          </div>
        ) : (
          <div>
            <p className="text-xs font-medium tracking-wider text-zinc-500">残り席</p>
            <p className={`text-5xl font-black leading-none tracking-tight tabular-nums ${flash ? 'price-flash' : ''}`}>
              <span aria-live="polite">{performance.remaining_seats}</span>
              <span className="ml-2 text-lg font-semibold text-zinc-500">/ {performance.capacity}</span>
            </p>
          </div>
        )}

        <p className="text-xs text-zinc-500" role="status">
          {connectionCopy(connectionState)} ・ 状態の確認 {relativeTime(lastSyncedAt)}
        </p>

        {notices.length > 0 && (
          <ul className="flex flex-col gap-2" aria-live="polite">
            {notices.map((notice) => (
              <li key={notice.id} className="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-sm text-sky-100">
                {notice.text}
              </li>
            ))}
          </ul>
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
          {joinState === 'waiting' && (
            <div className="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4">
              <p className="text-sm font-bold text-amber-300">{waitCopy.title}</p>
              <p className="mt-1 text-sm text-zinc-200">{waitCopy.body}</p>
              {(admission?.admitted_count ?? 0) > 0 && (
                <p className="mt-2 text-sm text-zinc-400">
                  他の人が枠を取れているのは先に並んだからです。あなたの待機は別です。空きが出れば先頭から通知します。
                </p>
              )}
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

          {isAuthenticated && isOpen && joinState === 'idle' && (
            <>
              <button
                type="button"
                onClick={() => {
                  void handleJoin();
                }}
                disabled={joinDisabled}
                aria-busy={submitting}
                className="rounded-lg bg-gradient-to-r from-violet-400 to-fuchsia-500 px-4 py-3 text-base font-extrabold text-zinc-950 shadow-lg shadow-fuchsia-500/30 transition active:scale-[.99] disabled:pointer-events-none disabled:opacity-50"
              >
                {submitting ? '枠を確認中…' : soldOut || released ? '待機列に並ぶ' : '待機列に並ぶ'}
              </button>
              {submitting && (
                <p className="text-sm text-zinc-400">残席があれば、この場で仮確保が入ります。二重に並ぶことはありません。</p>
              )}
              {released && <p className="text-sm text-zinc-400">取り消しや期限切れのあとでも、希望すればもう一度並べます。</p>}
            </>
          )}

          {isAuthenticated && isHeld && (
            <>
              <button
                type="button"
                onClick={() => {
                  void handleConfirm();
                }}
                disabled={confirming || cancelling}
                aria-busy={confirming}
                className="rounded-lg bg-gradient-to-r from-emerald-400 to-teal-500 px-4 py-3 text-base font-extrabold text-zinc-950 shadow-lg shadow-emerald-500/30 transition active:scale-[.99] disabled:pointer-events-none disabled:opacity-50"
              >
                {confirming ? '確定しています…' : 'この枠を確定する'}
              </button>
              <p className="text-sm text-zinc-400">確定は決済ではありません。確定後は TTL の対象外になり、ここからキャンセルできません。</p>
              {!cancelOpen ? (
                <button
                  type="button"
                  onClick={() => setCancelOpen(true)}
                  disabled={confirming || cancelling}
                  className="rounded-lg border border-zinc-700 bg-zinc-900 px-4 py-3 text-sm font-semibold text-zinc-200 hover:border-zinc-500"
                >
                  仮確保を取り消す
                </button>
              ) : (
                <div className="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4">
                  <p className="text-sm font-bold text-rose-100">この仮確保を手放しますか？</p>
                  <p className="mt-1 text-sm text-zinc-200">
                    席は待機列の先頭の人へ渡ります。抜けた扱いではなく、あなたが取り消したことが画面に残ります。確定済みにはできません。
                  </p>
                  <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                    <button
                      type="button"
                      onClick={() => setCancelOpen(false)}
                      disabled={cancelling}
                      className="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-zinc-200"
                    >
                      戻る
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        void handleCancel().then(() => setCancelOpen(false));
                      }}
                      disabled={cancelling}
                      aria-busy={cancelling}
                      className="rounded-lg bg-rose-400 px-4 py-2 text-sm font-extrabold text-zinc-950"
                    >
                      {cancelling ? '取り消しています…' : '取り消して席を渡す'}
                    </button>
                  </div>
                </div>
              )}
            </>
          )}

          {isAuthenticated && isConfirmed && (
            <p className="rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-400">
              確定済みです。同じ人がもう一度並んでも増えません。キャンセルも TTL もありません。
            </p>
          )}

          {isAuthenticated && isOpen && joinState === 'waiting' && (
            <p className="rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-400">
              すでに待機列に参加しています。空きが出れば並んだ順に仮確保が入り、その場でお知らせします。
            </p>
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
