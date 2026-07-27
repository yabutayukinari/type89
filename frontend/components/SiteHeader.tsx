'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { ReactNode } from 'react';
import { logoutUser, useUser } from '@/lib/auth';

type Tab = { href: string; label: string; icon: ReactNode };

// 浮遊ピルの1タブ幅 (px)。インジケータの移動量に使う。
const ITEM = 64;

const IconHome = (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 10.5 12 3l9 7.5" /><path d="M5 9.5V21h14V9.5" /></svg>
);
const IconTag = (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 12V4h8l10 10-8 8L3 12Z" /><circle cx="7.5" cy="7.5" r="1.5" /></svg>
);
const IconUser = (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="8" r="4" /><path d="M4 20c0-3.3 3.6-5.5 8-5.5s8 2.2 8 5.5" /></svg>
);
const IconLogin = (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M10 17l5-5-5-5" /><path d="M15 12H3" /><path d="M21 3v18" /></svg>
);

/**
 * 全ページ共通ナビ。
 * デスクトップ: 上部ヘッダー。モバイル: 画面下部に浮く「フローティング・ガラスピル」。
 * アクティブ表示はインジケータがモーフ移動する。出品はナビに常設せず、
 * 一覧ページの「出品する」ボタンから導線を張る。
 */
export function SiteHeader() {
  const pathname = usePathname();
  const router = useRouter();
  const { auth, refresh } = useUser();

  if (pathname?.startsWith('/admin')) {
    return null;
  }

  const isAuthed = auth.state === 'authenticated';

  const tabs: Tab[] = [
    { href: '/', label: 'ホーム', icon: IconHome },
    { href: '/auctions', label: 'オークション', icon: IconTag },
    isAuthed
      ? { href: '/me', label: 'マイページ', icon: IconUser }
      : { href: '/login', label: 'ログイン', icon: IconLogin },
  ];

  const matchIndex = (): number =>
    tabs.findIndex((t) =>
      t.href === '/'
        ? pathname === '/'
        : t.href === '/auctions'
          ? pathname === '/auctions' || pathname?.startsWith('/auctions/') === true
          : pathname === t.href,
    );
  const activeIndex = matchIndex();

  const handleLogout = async () => {
    await logoutUser();
    await refresh();
    router.push('/');
  };

  return (
    <>
      {/* デスクトップ: 上部ヘッダー (出品は常設しない) */}
      <header className="sticky top-0 z-40 border-b border-zinc-800 bg-zinc-950/85 text-zinc-100 backdrop-blur supports-[backdrop-filter]:bg-zinc-950/70">
        <div className="mx-auto flex h-14 w-full max-w-5xl items-center justify-between px-4">
          <Link href="/" className="flex items-center gap-1.5 font-extrabold tracking-tight">
            <span className="text-amber-400">⚡</span>
            type89 <span className="font-semibold text-zinc-400">Auctions</span>
          </Link>

          <nav className="hidden items-center gap-1 md:flex">
            {tabs
              .filter((t) => t.href !== '/')
              .map((tab) => {
                const active =
                  tab.href === '/auctions'
                    ? pathname === '/auctions' || pathname?.startsWith('/auctions/') === true
                    : pathname === tab.href;
                return (
                  <Link
                    key={tab.href}
                    href={tab.href}
                    className={
                      active
                        ? 'rounded-lg bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white'
                        : 'rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-400 hover:bg-zinc-900 hover:text-white'
                    }
                  >
                    {tab.label}
                  </Link>
                );
              })}
            {isAuthed && (
              <button
                type="button"
                onClick={handleLogout}
                className="ml-1 rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-400 hover:bg-zinc-900 hover:text-white"
              >
                ログアウト
              </button>
            )}
          </nav>
        </div>
      </header>

      {/* モバイル: フローティング・ガラスピル */}
      <nav
        aria-label="メインナビ"
        className="fixed bottom-[calc(env(safe-area-inset-bottom)+16px)] left-1/2 z-40 -translate-x-1/2 md:hidden"
      >
        <div
          className="relative grid rounded-full border border-white/10 bg-zinc-900/60 p-1.5 shadow-xl shadow-black/40 backdrop-blur-xl"
          style={{ gridTemplateColumns: `repeat(${tabs.length}, ${ITEM}px)` }}
        >
          {/* モーフ移動するアクティブインジケータ */}
          <span
            aria-hidden="true"
            className="absolute left-1.5 top-1.5 rounded-full bg-gradient-to-r from-orange-400 to-red-500 transition-transform duration-500 ease-[cubic-bezier(.34,1.56,.64,1)] motion-reduce:transition-none"
            style={{
              width: ITEM,
              height: 46,
              transform: `translateX(${Math.max(activeIndex, 0) * ITEM}px)`,
              opacity: activeIndex >= 0 ? 1 : 0,
            }}
          />
          {tabs.map((tab, i) => (
            <Link
              key={tab.href}
              href={tab.href}
              aria-current={i === activeIndex ? 'page' : undefined}
              className={`relative z-10 flex h-[46px] items-center justify-center transition-colors ${
                i === activeIndex ? 'text-zinc-950' : 'text-zinc-400'
              }`}
              title={tab.label}
            >
              <span className="h-6 w-6">{tab.icon}</span>
            </Link>
          ))}
        </div>
      </nav>
    </>
  );
}
