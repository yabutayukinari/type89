'use client';

import { AuctionStatus } from '@/lib/auctions';
import { useCountdown } from '@/lib/countdown';

/**
 * オークションの残り時間を 1 秒ごとに更新して表示する小さな client component。
 * 一覧の各行から呼べるよう、フック呼び出しをこのコンポーネントに閉じ込めている。
 */
export function AuctionCountdown({
  endsAt,
  status,
}: {
  endsAt: string;
  status: AuctionStatus;
}) {
  const { label, isOver, remainingMs } = useCountdown(endsAt);

  if (status === 'pending') {
    return <span className="text-xs text-zinc-500">開始前</span>;
  }

  if (status === 'ended' || isOver) {
    return <span className="text-xs text-zinc-500">終了</span>;
  }

  if (remainingMs <= 60 * 60 * 1000) {
    return (
      <span className="text-xs font-bold text-red-400">まもなく終了 {label}</span>
    );
  }

  return <span className="text-xs text-amber-600 dark:text-amber-400">残り {label}</span>;
}
