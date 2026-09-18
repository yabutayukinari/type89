'use client';

import Link from 'next/link';
import { use, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAdmin } from '@/lib/auth';
import { getEcho } from '@/lib/echo';
import { fetchAdminInventory, OrganizerEvent, OrganizerInventory } from '@/lib/performances';

type Props = { params: Promise<{ id: string }> };

const eventLabel = (event: OrganizerEvent): string => {
  if (event.type === 'held') {
    return '仮確保';
  }
  if (event.type === 'confirmed') {
    return '確定';
  }
  if (event.release_reason === 'ttl') {
    return '期限切れで解放';
  }
  return '本人キャンセルで解放';
};

export default function AdminPerformanceInventoryPage({ params }: Props) {
  const { id } = use(params);
  const performanceId = Number(id);
  const router = useRouter();
  const { auth } = useAdmin();
  const [inventory, setInventory] = useState<OrganizerInventory | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (auth.state === 'unauthenticated') {
      router.replace('/admin/login');
    }
  }, [auth.state, router]);

  useEffect(() => {
    if (auth.state !== 'authenticated') {
      return;
    }
    let cancelled = false;
    const load = (): void => {
      fetchAdminInventory(performanceId)
        .then((data) => {
          if (!cancelled) {
            setInventory(data);
            setError(null);
          }
        })
        .catch((err: unknown) => {
          if (!cancelled) {
            setError(err instanceof Error ? err.message : '在庫を取得できませんでした');
          }
        });
    };
    load();
    const echo = getEcho();
    const channel = echo.channel(`performance.${performanceId}`);
    channel.listen('.seats.updated', () => {
      load();
    });
    const timer = window.setInterval(load, 4000);
    return () => {
      cancelled = true;
      echo.leave(`performance.${performanceId}`);
      window.clearInterval(timer);
    };
  }, [auth.state, performanceId]);

  if (auth.state !== 'authenticated') {
    return (
      <main className="flex min-h-screen items-center justify-center p-8">
        <p className="text-zinc-600">読み込み中...</p>
      </main>
    );
  }

  return (
    <main className="mx-auto mt-12 flex w-full max-w-4xl flex-col gap-5 p-6 pb-16">
      <p className="text-sm text-zinc-500">
        <Link href="/admin/me" className="font-medium text-violet-700">
          管理画面
        </Link>
        <span className="px-1">/</span>
        <Link href="/admin/performances" className="font-medium text-violet-700">
          公演の在庫
        </Link>
      </p>
      {error && (
        <p role="alert" className="text-sm text-red-600">
          {error}
        </p>
      )}
      {inventory === null && !error && <p className="text-sm text-zinc-500">読み込み中...</p>}
      {inventory && (
        <>
          <header>
            <h1 className="text-2xl font-semibold">{inventory.performance.show.title}</h1>
            <p className="mt-1 text-sm text-zinc-600">
              会場スタッフ向け。残席の増減は仮確保の解放（キャンセル / TTL）か、FIFO の割り当てです。
            </p>
          </header>
          <dl className="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div className="rounded-xl border border-zinc-300 bg-white p-3">
              <dt className="text-xs text-zinc-500">残席</dt>
              <dd className="text-2xl font-bold tabular-nums">
                {inventory.remaining_seats}/{inventory.capacity}
              </dd>
            </div>
            <div className="rounded-xl border border-zinc-300 bg-white p-3">
              <dt className="text-xs text-zinc-500">仮確保</dt>
              <dd className="text-2xl font-bold tabular-nums">{inventory.held_count}</dd>
            </div>
            <div className="rounded-xl border border-zinc-300 bg-white p-3">
              <dt className="text-xs text-zinc-500">確定</dt>
              <dd className="text-2xl font-bold tabular-nums">{inventory.confirmed_count}</dd>
            </div>
            <div className="rounded-xl border border-zinc-300 bg-white p-3">
              <dt className="text-xs text-zinc-500">待機</dt>
              <dd className="text-2xl font-bold tabular-nums">{inventory.waiting_count}</dd>
            </div>
          </dl>
          <p className="text-sm text-zinc-600">仮確保 TTL は {Math.round(inventory.hold_ttl_seconds / 60)} 分。確定済みは期限切れになりません。</p>

          <section>
            <h2 className="text-lg font-semibold">いまの枠</h2>
            {inventory.current_slots.length === 0 ? (
              <p className="mt-2 text-sm text-zinc-500">仮確保・確定の枠はありません</p>
            ) : (
              <ul className="mt-2 divide-y divide-zinc-200 rounded-xl border border-zinc-300 bg-white">
                {inventory.current_slots.map((slot) => (
                  <li key={slot.id} className="flex flex-col gap-1 px-4 py-3 text-sm">
                    <p className="font-semibold">
                      {slot.status === 'confirmed' ? '確定' : '仮確保'} ・ {slot.user.name}
                    </p>
                    <p className="text-zinc-500">{slot.user.email}</p>
                    <p className="text-zinc-500">
                      確保 {new Date(slot.assigned_at).toLocaleString('ja-JP')}
                      {slot.expires_at ? ` ・ 期限 ${new Date(slot.expires_at).toLocaleString('ja-JP')}` : ''}
                      {slot.confirmed_at ? ` ・ 確定 ${new Date(slot.confirmed_at).toLocaleString('ja-JP')}` : ''}
                    </p>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section>
            <h2 className="text-lg font-semibold">確保 / 確定 / 解放</h2>
            {inventory.events.length === 0 ? (
              <p className="mt-2 text-sm text-zinc-500">まだ動きはありません</p>
            ) : (
              <ol className="mt-2 divide-y divide-zinc-200 rounded-xl border border-zinc-300 bg-white">
                {inventory.events.map((event) => (
                  <li key={event.id} className="px-4 py-3 text-sm">
                    <p className="font-semibold">
                      {eventLabel(event)} ・ {event.user.name}
                    </p>
                    <p className="text-zinc-500">{event.user.email}</p>
                    <p className="text-zinc-500">
                      {new Date(event.occurred_at).toLocaleString('ja-JP')} ・ その後の残席 {event.remaining_seats_after}
                    </p>
                  </li>
                ))}
              </ol>
            )}
          </section>
        </>
      )}
    </main>
  );
}
