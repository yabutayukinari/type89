import { api, ensureCsrfCookie } from './api';

export type AuctionSettledData = {
  auction_id: number;
  title: string;
  role: 'winner' | 'seller';
  has_winner: boolean;
  final_price: number;
  message: string;
};

export type AppNotification = {
  id: string;
  data: AuctionSettledData;
  read_at: string | null;
  created_at: string;
};

/**
 * Reverb 経由で届く通知ペイロード。Laravel の BroadcastNotificationCreated が
 * data 配列に id / type を merge したもの。
 */
export type BroadcastNotificationPayload = AuctionSettledData & {
  id: string;
  type: string;
};

export const fetchNotifications = async (): Promise<{
  items: AppNotification[];
  unreadCount: number;
}> => {
  const response = await api.get<{
    data: AppNotification[];
    meta: { unread_count: number };
  }>('/api/notifications');
  return { items: response.data.data, unreadCount: response.data.meta.unread_count };
};

export const markAllNotificationsRead = async (): Promise<void> => {
  await ensureCsrfCookie();
  await api.post('/api/notifications/read');
};
