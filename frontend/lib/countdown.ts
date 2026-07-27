'use client';

import { useEffect, useState } from 'react';

export type Countdown = {
  /** 残りミリ秒。終了済みなら 0。 */
  remainingMs: number;
  /** 「1日 2時間」「3分 05秒」のような日本語ラベル。終了済みなら「終了」。 */
  label: string;
  /** 終了時刻を過ぎているか。 */
  isOver: boolean;
};

export const formatRemaining = (remainingMs: number): string => {
  if (remainingMs <= 0) {
    return '終了';
  }

  const totalSeconds = Math.floor(remainingMs / 1000);
  const days = Math.floor(totalSeconds / 86400);
  const hours = Math.floor((totalSeconds % 86400) / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  if (days > 0) {
    return `${days}日 ${hours}時間`;
  }
  if (hours > 0) {
    return `${hours}時間 ${minutes}分`;
  }
  const mm = String(minutes).padStart(2, '0');
  const ss = String(seconds).padStart(2, '0');
  return `${mm}分 ${ss}秒`;
};

/**
 * 終了時刻 (ISO 文字列) までの残り時間を 1 秒ごとに更新して返す。
 * 終了済みになった瞬間 isOver が true になり、呼び出し側は UI を切り替えられる。
 */
export const useCountdown = (endsAt: string): Countdown => {
  const target = new Date(endsAt).getTime();
  const [remainingMs, setRemainingMs] = useState(() => Math.max(0, target - Date.now()));

  useEffect(() => {
    const tick = () => setRemainingMs(Math.max(0, target - Date.now()));
    tick();
    const timer = window.setInterval(tick, 1000);
    return () => window.clearInterval(timer);
  }, [target]);

  return {
    remainingMs,
    label: formatRemaining(remainingMs),
    isOver: remainingMs <= 0,
  };
};
