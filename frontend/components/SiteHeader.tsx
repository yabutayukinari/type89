'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { ReactNode } from 'react';
import { logoutUser, useUser } from '@/lib/auth';

type Tab = { href: string; label: string; icon: ReactNode };

const IconHome = (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 10.5 12 3l9 7.5" /><path d="M5 9.5V21h14V9.5" /></svg>
);
const IconTag = (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 12V4h8l10 10-8 8L3 12Z" /><circle cx="7.5" cy="7.5" r="1.5" /></svg>
);
const IconPlus = (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></svg>
);
const IconUser = (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="8" r="4" /><path d="M4 20c0-3.3 3.6-5.5 8-5.5s8 2.2 8 5.5" /></svg>
);
const IconLogin = (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M10 17l5-5-5-5" /><path d="M15 12H3" /><path d="M21 3v18" /></svg>
);

/**
 * 全ページ共通ナビ。
 * デスクトップ: 上部の水平ヘッダー。
 * モバイル: 画面下部のタブバー (親指で届く一次ナビ)。ハンバーガーは使わない。
 * 認証状態 (useUser) でメニューを出し分ける。
 */
export function SiteHeader() {
  const pathname = usePathname();
  const router = useRouter();
  const { auth, refresh } = useUser();

  // 管理画面は別系統の認証なので、このユーザー向けナビは出さない。
  if (pathname?.startsWith('/admin')) {
    return null;
  }

  const isAuthed = auth.state === 'authenticated';

  const tabs: Tab[] = [
    { href: '/', label: 'ホーム', icon: IconHome },
    { href: '/auctions', label: 'オークション', icon: IconTag },
    ...(isAuthed
      ? [
          { href: '/auctions/new', label: '出品', icon: IconPlus },
          { href: '/me', label: 'マイページ', icon: IconUser },
        ]
      : [{ href: '/login', label: 'ログイン', icon: IconLogin }]),
  ];

  const isActive = (href: string): boolean => {
    if (href === '/') return pathname === '/';
    if (href === '/auctions') {
      return pathname === '/auctions' || pathname?.startsWith('/auctions/') === true
        ? pathname !== '/auctions/new'
        : false;
    }
    return pathname === href;
  };

  const handleLogout = async () => {
    await logoutUser();
    await refresh();
    router.push('/');
  };

  return (
    <>
      {/* 上部ヘッダー: ブランドは全幅、ナビリンクはデスクトップのみ */}
      <header className="sticky top-0 z-40 border-b border-zinc-800 bg-zinc-950/85 text-zinc-100 backdrop-blur supports-[backdrop-filter]:bg-zinc-950/70">
        <div className="mx-auto flex h-14 w-full max-w-5xl items-center justify-between px-4">
          <Link href="/" className="flex items-center gap-1.5 font-extrabold tracking-tight">
            <span className="text-amber-400">⚡</span>
            type89 <span className="font-semibold text-zinc-400">Auctions</span>
          </Link>

          <nav className="hidden items-center gap-1 md:flex">
            {tabs
              .filter((t) => t.href !== '/')
              .map((tab) =>
                tab.href === '/auctions/new' ? (
                  <Link
                    key={tab.href}
                    href={tab.href}
                    className="rounded-lg bg-gradient-to-r from-orange-400 to-red-500 px-3 py-1.5 text-sm font-bold text-zinc-950"
                  >
                    出品する
                  </Link>
                ) : (
                  <Link
                    key={tab.href}
                    href={tab.href}
                    className={
                      isActive(tab.href)
                        ? 'rounded-lg bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white'
                        : 'rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-400 hover:bg-zinc-900 hover:text-white'
                    }
                  >
                    {tab.label}
                  </Link>
                ),
              )}
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

      {/* 下部タブバー: モバイルのみ */}
      <nav
        aria-label="メインナビ"
        className="fixed inset-x-0 bottom-0 z-40 grid border-t border-zinc-800 bg-zinc-950/95 pb-[env(safe-area-inset-bottom)] text-zinc-400 backdrop-blur md:hidden"
        style={{ gridTemplateColumns: `repeat(${tabs.length}, minmax(0, 1fr))` }}
      >
        {tabs.map((tab) => {
          const active = isActive(tab.href);
          return (
            <Link
              key={tab.href}
              href={tab.href}
              aria-current={active ? 'page' : undefined}
              className={`flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium ${
                active ? 'text-amber-400' : 'text-zinc-400'
              }`}
            >
              <span className="h-6 w-6">{tab.icon}</span>
              {tab.label}
            </Link>
          );
        })}
      </nav>
    </>
  );
}
