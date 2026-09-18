export type SaleStatus = 'upcoming' | 'open' | 'closed';
export type QueueEntryStatus = 'waiting' | 'admitted';
export type QueueWaitReason = 'sold_out' | 'others_ahead' | 'assigning';
export type EchoConnectionState = 'live' | 'reconnecting' | 'offline';

export type ShowSummary = {
  id: number;
  title: string;
  description: string;
  venue_label: string;
};

export type Performance = {
  id: number;
  price: number;
  starts_at: string;
  sale_opens_at: string;
  sale_closes_at: string;
  sale_status: SaleStatus;
  capacity: number;
  remaining_seats: number;
  inventory_updated_at: string | null;
  show: ShowSummary;
};

export type QueueEntry = {
  id: number;
  status: QueueEntryStatus;
  position: number;
  joined_at: string;
};

export type PurchaseSlot = {
  id: number;
  assigned_at: string;
};

export type QueueAdmission = {
  performance: Performance;
  queue_entry: QueueEntry | null;
  purchase_slot: PurchaseSlot | null;
  waiting_ahead: number;
  waiting_count: number;
  admitted_count: number;
  wait_reason: QueueWaitReason | null;
};

export type SeatsUpdatedPayload = {
  performance_id: number;
  capacity: number;
  remaining_seats: number;
  inventory_updated_at?: string | null;
};

export type SlotAssignedPayload = {
  purchase_slot: {
    id: number;
    performance_id: number;
    assigned_at: string;
  };
  queue_entry: {
    id: number;
    position: number;
    status: QueueEntryStatus;
  };
};

export type SeatSnapshot = {
  remaining_seats: number;
  inventory_updated_at: string | null;
};

export type QueueNotice = {
  id: string;
  text: string;
};

const QUEUE_CACHE_PREFIX = 'type89.ticket-queue.v1';

export const queueCacheKey = (userId: number, performanceId: number): string =>
  `${QUEUE_CACHE_PREFIX}.${userId}.${performanceId}`;

export const readQueueCache = (userId: number, performanceId: number): QueueAdmission | null => {
  if (typeof window === 'undefined') {
    return null;
  }
  try {
    const raw = window.localStorage.getItem(queueCacheKey(userId, performanceId));
    if (raw === null) {
      return null;
    }
    const parsed: unknown = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object' || !('performance' in parsed)) {
      return null;
    }
    return parsed as QueueAdmission;
  } catch {
    return null;
  }
};

export const writeQueueCache = (
  userId: number,
  performanceId: number,
  admission: QueueAdmission,
): void => {
  if (typeof window === 'undefined') {
    return;
  }
  try {
    window.localStorage.setItem(queueCacheKey(userId, performanceId), JSON.stringify(admission));
  } catch {
    // private mode / quota — live API remains the source of truth
  }
};

/**
 * Remaining seats only decrease in this demo. Ignore delayed Reverb payloads
 * that would flash a higher remaining count after reconnect or out-of-order delivery.
 */
export const shouldApplySeatUpdate = (current: SeatSnapshot, incoming: SeatSnapshot): boolean => {
  if (incoming.remaining_seats > current.remaining_seats) {
    return false;
  }
  const currentAt = current.inventory_updated_at ? Date.parse(current.inventory_updated_at) : Number.NaN;
  const incomingAt = incoming.inventory_updated_at ? Date.parse(incoming.inventory_updated_at) : Number.NaN;
  if (!Number.isNaN(currentAt) && !Number.isNaN(incomingAt) && incomingAt < currentAt) {
    return false;
  }
  return true;
};

export const applySeatUpdate = (performance: Performance, incoming: SeatsUpdatedPayload): Performance => {
  const next: SeatSnapshot = {
    remaining_seats: incoming.remaining_seats,
    inventory_updated_at: incoming.inventory_updated_at ?? performance.inventory_updated_at,
  };
  if (
    !shouldApplySeatUpdate(
      {
        remaining_seats: performance.remaining_seats,
        inventory_updated_at: performance.inventory_updated_at,
      },
      next,
    )
  ) {
    return performance;
  }
  return {
    ...performance,
    remaining_seats: incoming.remaining_seats,
    capacity: incoming.capacity,
    inventory_updated_at: next.inventory_updated_at,
  };
};

/**
 * Slots are never revoked. Keep a locally known slot if a refetch/event would
 * flash it as missing, and never swap in a second slot id for the same user.
 */
export const mergeAdmission = (previous: QueueAdmission | null, incoming: QueueAdmission): QueueAdmission => {
  if (previous?.purchase_slot && incoming.purchase_slot && previous.purchase_slot.id !== incoming.purchase_slot.id) {
    return { ...incoming, purchase_slot: previous.purchase_slot };
  }
  if (previous?.purchase_slot && incoming.purchase_slot === null) {
    const queueEntry = incoming.queue_entry ?? previous.queue_entry;
    return {
      ...incoming,
      purchase_slot: previous.purchase_slot,
      queue_entry: queueEntry
        ? { ...queueEntry, status: 'admitted' }
        : queueEntry,
    };
  }
  return incoming;
};

export const waitReasonCopy = (
  reason: QueueWaitReason | null,
  admittedCount: number,
  waitingAhead: number,
): { title: string; body: string } => {
  if (reason === 'assigning') {
    return {
      title: '枠を確認しています',
      body: '残席はあります。先に並んだ人から割り当てている途中です。失敗ではありません。',
    };
  }
  if (reason === 'others_ahead') {
    return {
      title: '先に並んだ人が優先です',
      body: `あなたの前に ${waitingAhead} 人が待っています。残席があればその順で枠が入ります。`,
    };
  }
  if (reason === 'sold_out') {
    const others = admittedCount > 0 ? `すでに ${admittedCount} 人が枠を確保しています。` : '';
    const ahead = waitingAhead > 0 ? `あなたの前にも ${waitingAhead} 人が待っています。` : '';
    return {
      title: 'キャンセル待ちで待機中',
      body: `${others}${ahead}いまは満席です。空席が開けば並んだ順に割り当てます。番号そのものは減りません。`.trim(),
    };
  }
  return {
    title: '待機中',
    body: '残席があれば枠はすぐに入ります。空席が開けば、その時点であなたに割り当てられます。',
  };
};

export const connectionCopy = (state: EchoConnectionState): string => {
  if (state === 'live') {
    return 'ライブ接続中';
  }
  if (state === 'reconnecting') {
    return '再接続中。残席は確定するまで更新しません';
  }
  return '接続が切れています。残席は最新ではない可能性があります';
};

export const seatChangeNotice = (
  previousRemaining: number | null,
  nextRemaining: number,
  hasSlot: boolean,
): string | null => {
  if (previousRemaining === null || previousRemaining === nextRemaining) {
    return null;
  }
  if (hasSlot) {
    return `他の人向けの残り席が ${previousRemaining} → ${nextRemaining} になりました。あなたの枠は確保済みのままです。`;
  }
  if (nextRemaining < previousRemaining) {
    return `残席が ${previousRemaining} → ${nextRemaining} になりました。他の購入者が枠を取りました。あなたはまだ待機列にいます。`;
  }
  return `残席の表示を ${previousRemaining} → ${nextRemaining} に更新しました（再取得）。`;
};

export const waitingAheadNotice = (previous: number | null, next: number): string | null => {
  if (previous === null || previous === next) {
    return null;
  }
  if (next < previous) {
    return `前の待機が ${previous} 人 → ${next} 人になりました。列は進んでいます。`;
  }
  return `前の待機が ${previous} 人 → ${next} 人になりました。割り込みではありません。再取得した人数です。`;
};
