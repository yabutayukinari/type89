'use client';

import Link from 'next/link';
import { useNotifications } from '@/lib/useNotifications';

/**
 * ユーザーダッシュボード用の通知パネル。
 * 落札確定などの通知を一覧表示し、Reverb 経由の新着をリアルタイムに反映する。
 */
export function NotificationsPanel({ userId }: { userId: number }) {
  const { items, unreadCount, markAllRead } = useNotifications(userId);

  return (
    <section className="flex flex-col gap-3">
      <div className="flex items-center justify-between">
        <h2 className="flex items-center gap-2 text-base font-semibold">
          通知
          {unreadCount > 0 && (
            <span
              data-testid="unread-badge"
              className="rounded-full bg-red-600 px-2 py-0.5 text-xs font-medium text-white"
            >
              {unreadCount}
            </span>
          )}
        </h2>
        {unreadCount > 0 && (
          <button
            type="button"
            onClick={markAllRead}
            className="text-xs text-zinc-500 underline hover:text-zinc-800 dark:hover:text-zinc-200"
          >
            すべて既読
          </button>
        )}
      </div>

      {items.length === 0 ? (
        <p className="text-sm text-zinc-500">通知はまだありません</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {items.map((n) => (
            <li
              key={n.id}
              className={`rounded border p-3 text-sm ${
                n.read_at
                  ? 'border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950'
                  : 'border-amber-300 bg-amber-50 dark:border-amber-800/60 dark:bg-amber-950/30'
              }`}
            >
              <Link href={`/auctions/${n.data.auction_id}`} className="hover:underline">
                {n.data.message}
              </Link>
              <p className="mt-1 text-xs text-zinc-500">
                {new Date(n.created_at).toLocaleString('ja-JP')}
              </p>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
