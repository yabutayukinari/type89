export type SaleStatus = 'upcoming' | 'open' | 'closed';
export type QueueEntryStatus = 'waiting' | 'admitted' | 'confirmed' | 'cancelled' | 'expired';
export type PurchaseSlotStatus = 'held' | 'confirmed';
export type QueueWaitReason = 'sold_out' | 'others_ahead' | 'assigning';
export type EchoConnectionState = 'live' | 'reconnecting' | 'offline';
export type SeatUpdateReason = 'assigned' | 'released';
export type SlotReleaseReason = 'self_cancel' | 'ttl';

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
  hold_ttl_seconds: number;
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
  status: PurchaseSlotStatus;
  assigned_at: string;
  expires_at: string | null;
  confirmed_at: string | null;
};

export type SlotRelease = {
  reason: SlotReleaseReason;
  released_at: string;
};

export type QueueAdmission = {
  performance: Performance;
  queue_entry: QueueEntry | null;
  purchase_slot: PurchaseSlot | null;
  slot_release: SlotRelease | null;
  hold_ttl_seconds: number;
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
  reason?: SeatUpdateReason;
  release_reason?: SlotReleaseReason | null;
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
  reason?: SeatUpdateReason;
};

export type QueueNotice = {
  id: string;
  text: string;
};

const QUEUE_CACHE_PREFIX = 'type89.ticket-queue.v2';

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

const isNewerOrEqualTimestamp = (current: SeatSnapshot, incoming: SeatSnapshot): boolean => {
  const currentAt = current.inventory_updated_at ? Date.parse(current.inventory_updated_at) : Number.NaN;
  const incomingAt = incoming.inventory_updated_at ? Date.parse(incoming.inventory_updated_at) : Number.NaN;
  if (!Number.isNaN(currentAt) && !Number.isNaN(incomingAt) && incomingAt < currentAt) {
    return false;
  }
  return true;
};

/**
 * Remaining seats decrease on assign and increase only on explained release.
 * Ignore delayed Reverb payloads that would bounce remaining without a release reason.
 */
export const shouldApplySeatUpdate = (current: SeatSnapshot, incoming: SeatSnapshot): boolean => {
  if (!isNewerOrEqualTimestamp(current, incoming)) {
    return false;
  }
  if (incoming.remaining_seats > current.remaining_seats) {
    return incoming.reason === 'released';
  }
  return true;
};

export const applySeatUpdate = (performance: Performance, incoming: SeatsUpdatedPayload): Performance => {
  const next: SeatSnapshot = {
    remaining_seats: incoming.remaining_seats,
    inventory_updated_at: incoming.inventory_updated_at ?? performance.inventory_updated_at,
    reason: incoming.reason,
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

const isExplainedRelease = (admission: QueueAdmission): boolean => {
  const status = admission.queue_entry?.status;
  return status === 'cancelled' || status === 'expired' || admission.slot_release !== null;
};

/**
 * Authoritative 200 responses win. Cached holds are kept only when the server
 * omits a slot without explaining a cancel or TTL release.
 */
export const mergeAdmission = (previous: QueueAdmission | null, incoming: QueueAdmission): QueueAdmission => {
  if (!previous?.purchase_slot) {
    return incoming;
  }
  if (incoming.purchase_slot) {
    return incoming;
  }
  if (isExplainedRelease(incoming)) {
    return incoming;
  }
  return {
    ...incoming,
    purchase_slot: previous.purchase_slot,
    queue_entry: previous.queue_entry ?? incoming.queue_entry,
  };
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
      body: `あなたの前に ${waitingAhead} 人が待っています。残席があればその順で枠が入ります。仮確保の期限切れや取り消しがあれば、先頭から順に入ります。`,
    };
  }
  if (reason === 'sold_out') {
    const others = admittedCount > 0 ? `すでに ${admittedCount} 人が枠を確保しています。` : '';
    const ahead = waitingAhead > 0 ? `あなたの前にも ${waitingAhead} 人が待っています。` : '';
    return {
      title: '空き待ちで待機中',
      body: `${others}${ahead}いまは満席です。仮確保の取り消しや期限切れで空席が開けば、並んだ順に割り当てます。番号そのものは減りません。`.trim(),
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

export const holdTtlCopy = (seconds: number): string => {
  const minutes = Math.max(1, Math.round(seconds / 60));
  return `仮確保の期限は ${minutes} 分です。期限内に「確定」しないと、枠は待機列の先頭へ渡ります。抜けた扱いではありません。`;
};

export const slotReleaseCopy = (release: SlotRelease | null, status: QueueEntryStatus | undefined): { title: string; body: string } | null => {
  if (status === 'cancelled' || release?.reason === 'self_cancel') {
    return {
      title: '枠の取り消しが完了しました',
      body: 'あなたの操作で仮確保を手放しました。席は待機列の先頭の人へ渡りました。画面から勝手に消えたわけではありません。',
    };
  }
  if (status === 'expired' || release?.reason === 'ttl') {
    return {
      title: '仮確保の期限が切れました',
      body: '確定前の仮確保は時間切れで解放されます。席は待機列の先頭へ渡りました。抜けた扱いではなく、期限内に確定しなかったためです。',
    };
  }
  return null;
};

export const seatChangeNotice = (
  previousRemaining: number | null,
  nextRemaining: number,
  hasSlot: boolean,
  reason?: SeatUpdateReason,
): string | null => {
  if (previousRemaining === null || previousRemaining === nextRemaining) {
    return null;
  }
  if (nextRemaining > previousRemaining) {
    const cause = '誰かが仮確保を取り消したか、期限が切れたためです。';
    if (hasSlot) {
      return `他の人向けの残り席が ${previousRemaining} → ${nextRemaining} になりました。${cause}あなたの枠は確保済みのままです。`;
    }
    return `残席が ${previousRemaining} → ${nextRemaining} になりました。${cause}空きが出れば並んだ順に枠が入ります。`;
  }
  if (hasSlot) {
    return `他の人向けの残り席が ${previousRemaining} → ${nextRemaining} になりました。あなたの枠は確保済みのままです。`;
  }
  if (reason === 'assigned') {
    return `残席が ${previousRemaining} → ${nextRemaining} になりました。他の購入者が枠を取りました。あなたはまだ待機列にいます。`;
  }
  return `残席が ${previousRemaining} → ${nextRemaining} になりました。他の購入者が枠を取りました。あなたはまだ待機列にいます。`;
};

export const waitingAheadNotice = (previous: number | null, next: number): string | null => {
  if (previous === null || previous === next) {
    return null;
  }
  if (next < previous) {
    return `前の待機が ${previous} 人 → ${next} 人になりました。列は進んでいます。空きが出れば先頭から枠が入ります。`;
  }
  return `前の待機が ${previous} 人 → ${next} 人になりました。割り込みではありません。再取得した人数です。`;
};

export const admissionTurnNotice = (
  previous: QueueAdmission | null,
  incoming: QueueAdmission,
): string | null => {
  if (previous?.purchase_slot || !incoming.purchase_slot) {
    return null;
  }
  if (previous?.queue_entry?.status !== 'waiting') {
    return null;
  }
  return '空きが出たので、並んだ順であなたの枠が入りました。残り時間内に確定してください。';
};
