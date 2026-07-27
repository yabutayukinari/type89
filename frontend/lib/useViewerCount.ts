'use client';

import { useEffect, useState } from 'react';
import { getEcho } from './echo';

type PresenceMember = { id: number; name: string };

/**
 * オークションの Presence チャンネルに join し、観戦中の人数を返す。
 *
 * Presence チャンネルは認証必須のため、enabled (= ログイン済み) のときだけ join する。
 * 匿名の閲覧者は人数に含まれず、その場合は null を返す。
 */
export const useViewerCount = (auctionId: number, enabled: boolean): number | null => {
  const [count, setCount] = useState<number | null>(null);

  useEffect(() => {
    if (!enabled) {
      return;
    }

    const echo = getEcho();
    const name = `auction-presence.${auctionId}`;

    echo
      .join(name)
      .here((members: PresenceMember[]) => setCount(members.length))
      .joining(() => setCount((current) => (current ?? 0) + 1))
      .leaving(() => setCount((current) => (current && current > 0 ? current - 1 : 0)));

    return () => {
      echo.leave(name);
      setCount(null);
    };
  }, [auctionId, enabled]);

  return enabled ? count : null;
};
