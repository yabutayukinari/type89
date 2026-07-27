'use client';

import { useCallback, useEffect, useState } from 'react';
import { getEcho } from './echo';
import {
  AppNotification,
  BroadcastNotificationPayload,
  fetchNotifications,
  markAllNotificationsRead,
} from './notifications';

/**
 * ログインユーザーの通知を管理するフック。
 *
 * 初回に API から履歴を取得し、その後 user.{id} private チャンネルを購読して
 * Reverb 経由で届く新着通知をリアルタイムに先頭へ差し込む。
 */
export const useNotifications = (
  userId: number | null,
): {
  items: AppNotification[];
  unreadCount: number;
  markAllRead: () => Promise<void>;
} => {
  const [items, setItems] = useState<AppNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);

  useEffect(() => {
    if (userId === null) {
      return;
    }
    let cancelled = false;
    fetchNotifications().then(({ items: fetched, unreadCount: unread }) => {
      if (!cancelled) {
        setItems(fetched);
        setUnreadCount(unread);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [userId]);

  useEffect(() => {
    if (userId === null) {
      return;
    }
    const echo = getEcho();
    const channelName = `user.${userId}`;

    echo.private(channelName).notification((payload: BroadcastNotificationPayload) => {
      const incoming: AppNotification = {
        id: payload.id,
        data: {
          auction_id: payload.auction_id,
          title: payload.title,
          role: payload.role,
          has_winner: payload.has_winner,
          final_price: payload.final_price,
          message: payload.message,
        },
        read_at: null,
        created_at: new Date().toISOString(),
      };
      setItems((prev) => [incoming, ...prev]);
      setUnreadCount((count) => count + 1);
    });

    return () => {
      echo.leave(channelName);
    };
  }, [userId]);

  const markAllRead = useCallback(async () => {
    await markAllNotificationsRead();
    const readAt = new Date().toISOString();
    setItems((prev) => prev.map((n) => ({ ...n, read_at: n.read_at ?? readAt })));
    setUnreadCount(0);
  }, []);

  return { items, unreadCount, markAllRead };
};
