'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { extractApiMessage } from './apiError';
import { AuthState, User } from './auth';
import { getEcho } from './echo';
import { subscribeEchoConnection } from './echoConnection';
import { fetchPerformance, fetchQueueStatus, joinQueue } from './performances';
import {
  applySeatUpdate,
  EchoConnectionState,
  mergeAdmission,
  Performance,
  QueueAdmission,
  QueueNotice,
  readQueueCache,
  seatChangeNotice,
  SeatsUpdatedPayload,
  SlotAssignedPayload,
  waitingAheadNotice,
  writeQueueCache,
} from './ticketQueueTrust';

type UseTicketQueueResult = {
  performance: Performance | null;
  admission: QueueAdmission | null;
  error: string | null;
  submitting: boolean;
  ready: boolean;
  flash: boolean;
  notices: QueueNotice[];
  connectionState: EchoConnectionState;
  inventoryStale: boolean;
  lastSyncedAt: number | null;
  handleJoin: () => Promise<void>;
};

const MAX_NOTICES = 3;

const pushNotice = (current: QueueNotice[], text: string): QueueNotice[] => {
  const next: QueueNotice = { id: `${Date.now()}-${text}`, text };
  return [...current, next].slice(-MAX_NOTICES);
};

export const useTicketQueue = (
  performanceId: number,
  auth: AuthState<User>,
): UseTicketQueueResult => {
  const [livePerformance, setLivePerformance] = useState<Performance | null>(null);
  const [liveAdmission, setLiveAdmission] = useState<QueueAdmission | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [queueResolved, setQueueResolved] = useState(false);
  const [flash, setFlash] = useState(false);
  const [notices, setNotices] = useState<QueueNotice[]>([]);
  const [connectionState, setConnectionState] = useState<EchoConnectionState>('reconnecting');
  const [lastSyncedAt, setLastSyncedAt] = useState<number | null>(null);

  const joinLock = useRef(false);
  const flashTimer = useRef<number | null>(null);
  const prevRemaining = useRef<number | null>(null);
  const prevWaitingAhead = useRef<number | null>(null);
  const connectionStateRef = useRef(connectionState);
  const liveAdmissionRef = useRef<QueueAdmission | null>(null);
  const fetchGen = useRef(0);

  const userId = auth.state === 'authenticated' ? auth.principal.id : null;
  const isAuthenticated = auth.state === 'authenticated';
  const cachedAdmission = userId !== null ? readQueueCache(userId, performanceId) : null;
  const admission = liveAdmission ?? cachedAdmission;
  const performance = livePerformance ?? cachedAdmission?.performance ?? null;

  useEffect(() => {
    connectionStateRef.current = connectionState;
    liveAdmissionRef.current = liveAdmission;
  }, [connectionState, liveAdmission]);

  const bumpFlash = useCallback((): void => {
    setFlash(true);
    if (flashTimer.current !== null) {
      window.clearTimeout(flashTimer.current);
    }
    flashTimer.current = window.setTimeout(() => setFlash(false), 900);
  }, []);

  const noteSeatAndQueueChanges = useCallback(
    (nextAdmission: QueueAdmission): void => {
      const remaining = nextAdmission.performance.remaining_seats;
      const hasSlot = nextAdmission.purchase_slot != null;
      const remainingNotice = seatChangeNotice(prevRemaining.current, remaining, hasSlot);
      if (remainingNotice) {
        setNotices((current) => pushNotice(current, remainingNotice));
        bumpFlash();
      }
      if (nextAdmission.queue_entry !== null && nextAdmission.purchase_slot === null) {
        const aheadNotice = waitingAheadNotice(prevWaitingAhead.current, nextAdmission.waiting_ahead);
        if (aheadNotice) {
          setNotices((current) => pushNotice(current, aheadNotice));
        }
      }
      prevWaitingAhead.current = nextAdmission.waiting_ahead;
      prevRemaining.current = remaining;
    },
    [bumpFlash],
  );

  const commitAdmission = useCallback(
    (incoming: QueueAdmission, options?: { ignoreStaleSeats?: boolean; gen?: number }) => {
      if (options?.gen !== undefined && options.gen !== fetchGen.current) {
        return;
      }
      const previous = liveAdmissionRef.current ?? (userId !== null ? readQueueCache(userId, performanceId) : null);
      const merged = mergeAdmission(previous, incoming);
      const nextPerformance =
        options?.ignoreStaleSeats && previous
          ? applySeatUpdate(previous.performance, {
              performance_id: merged.performance.id,
              capacity: merged.performance.capacity,
              remaining_seats: merged.performance.remaining_seats,
              inventory_updated_at: merged.performance.inventory_updated_at,
            })
          : merged.performance;
      const next = { ...merged, performance: nextPerformance };
      liveAdmissionRef.current = next;
      if (userId !== null) {
        writeQueueCache(userId, performanceId, next);
      }
      noteSeatAndQueueChanges(next);
      setLiveAdmission(next);
      setLivePerformance((current) => {
        const baseline = current ?? previous?.performance ?? null;
        if (!baseline || !options?.ignoreStaleSeats) {
          return next.performance;
        }
        return applySeatUpdate(baseline, {
          performance_id: incoming.performance.id,
          capacity: incoming.performance.capacity,
          remaining_seats: incoming.performance.remaining_seats,
          inventory_updated_at: incoming.performance.inventory_updated_at,
        });
      });
      setLastSyncedAt(Date.now());
      setQueueResolved(true);
    },
    [noteSeatAndQueueChanges, performanceId, userId],
  );

  const refreshStatus = useCallback(async (): Promise<void> => {
    if (!isAuthenticated) {
      return;
    }
    const gen = fetchGen.current + 1;
    fetchGen.current = gen;
    const data = await fetchQueueStatus(performanceId);
    commitAdmission(data, { ignoreStaleSeats: connectionStateRef.current !== 'live', gen });
  }, [commitAdmission, isAuthenticated, performanceId]);

  useEffect(() => {
    if (auth.state === 'loading') {
      return;
    }
    let cancelled = false;
    if (auth.state === 'unauthenticated') {
      fetchPerformance(performanceId).then((data) => {
        if (!cancelled) {
          setLivePerformance(data);
          setLiveAdmission(null);
          setQueueResolved(true);
        }
      });
      return () => {
        cancelled = true;
      };
    }
    fetchQueueStatus(performanceId)
      .then((data) => {
        if (!cancelled) {
          const gen = fetchGen.current + 1;
          fetchGen.current = gen;
          commitAdmission(data, { gen });
        }
      })
      .catch(() => {
        if (!cancelled) {
          fetchPerformance(performanceId).then((data) => {
            if (!cancelled) {
              setLivePerformance(data);
              setQueueResolved(true);
            }
          });
        }
      });
    return () => {
      cancelled = true;
    };
  }, [auth.state, commitAdmission, performanceId]);

  useEffect(() => {
    const echo = getEcho();
    return subscribeEchoConnection(echo, setConnectionState);
  }, []);

  const wasLive = useRef(false);
  useEffect(() => {
    if (connectionState === 'live') {
      if (wasLive.current) {
        void refreshStatus();
      }
      wasLive.current = true;
    }
  }, [connectionState, refreshStatus]);

  useEffect(() => {
    const echo = getEcho();
    const channel = echo.channel(`performance.${performanceId}`);
    channel.listen('.seats.updated', (payload: SeatsUpdatedPayload) => {
      setLivePerformance((prev) => (prev ? applySeatUpdate(prev, payload) : prev));
      setLiveAdmission((prev) =>
        prev ? { ...prev, performance: applySeatUpdate(prev.performance, payload) } : prev,
      );
      if (isAuthenticated) {
        void refreshStatus();
        return;
      }
      const remainingNotice = seatChangeNotice(prevRemaining.current, payload.remaining_seats, false);
      if (remainingNotice) {
        setNotices((current) => pushNotice(current, remainingNotice));
        bumpFlash();
      }
      prevRemaining.current = payload.remaining_seats;
    });
    return () => {
      echo.leave(`performance.${performanceId}`);
    };
  }, [bumpFlash, isAuthenticated, performanceId, refreshStatus]);

  useEffect(() => {
    if (!isAuthenticated || userId === null) {
      return;
    }
    const echo = getEcho();
    const channelName = `user.${userId}`;
    const channel = echo.private(channelName);
    const onAssigned = (payload: SlotAssignedPayload): void => {
      if (payload.purchase_slot.performance_id !== performanceId) {
        return;
      }
      void refreshStatus();
    };
    const onQueueUpdated = (payload: QueueAdmission): void => {
      if (payload.performance.id !== performanceId) {
        return;
      }
      const gen = fetchGen.current + 1;
      fetchGen.current = gen;
      commitAdmission(payload, { gen });
    };
    channel.listen('.slot.assigned', onAssigned);
    channel.listen('.queue.updated', onQueueUpdated);
    return () => {
      echo.leave(channelName);
    };
  }, [commitAdmission, isAuthenticated, performanceId, refreshStatus, userId]);

  useEffect(() => {
    if (userId === null) {
      return;
    }
    const key = `type89.ticket-queue.v1.${userId}.${performanceId}`;
    const onStorage = (event: StorageEvent): void => {
      if (event.key !== key || event.newValue === null) {
        return;
      }
      try {
        const gen = fetchGen.current + 1;
        fetchGen.current = gen;
        commitAdmission(JSON.parse(event.newValue) as QueueAdmission, { gen });
      } catch {
        // ignore malformed cache from another tab
      }
    };
    window.addEventListener('storage', onStorage);
    return () => window.removeEventListener('storage', onStorage);
  }, [commitAdmission, performanceId, userId]);

  const waitingWithoutSlot = admission?.queue_entry !== null && admission?.purchase_slot === null;
  useEffect(() => {
    if (!waitingWithoutSlot || !isAuthenticated) {
      return;
    }
    const timer = window.setInterval(() => {
      void refreshStatus();
    }, 2000);
    return () => window.clearInterval(timer);
  }, [waitingWithoutSlot, isAuthenticated, refreshStatus]);

    const handleJoin = async (): Promise<void> => {
    if (joinLock.current) {
      return;
    }
    joinLock.current = true;
    setError(null);
    setSubmitting(true);
    try {
      const gen = fetchGen.current + 1;
      fetchGen.current = gen;
      const next = await joinQueue(performanceId);
      commitAdmission(next, { gen });
    } catch (err: unknown) {
      try {
        const gen = fetchGen.current + 1;
        fetchGen.current = gen;
        const recovered = await fetchQueueStatus(performanceId);
        if (recovered.queue_entry !== null) {
          commitAdmission(recovered, { gen });
          setError(null);
          return;
        }
      } catch {
        // fall through to the original join error
      }
      joinLock.current = false;
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

  const authPending = auth.state === 'loading';
  const queuePending = auth.state === 'authenticated' && !queueResolved && admission === null;
  const ready = performance !== null && !authPending && !queuePending;
  const inventoryStale = connectionState !== 'live';

  return {
    performance,
    admission: isAuthenticated ? admission : null,
    error,
    submitting,
    ready,
    flash,
    notices,
    connectionState,
    inventoryStale,
    lastSyncedAt,
    handleJoin,
  };
};
