'use client';

import { ReactNode } from 'react';

type Props = {
  title: string;
  imageUrl: string | null | undefined;
  children?: ReactNode;
  className?: string;
};

const palettes = [
  'from-[#3a2410] to-[#0c0f14]',
  'from-[#1a2740] to-[#0c0f14]',
  'from-[#2a1020] to-[#0c0f14]',
  'from-[#102a1c] to-[#0c0f14]',
  'from-[#2a2108] to-[#0c0f14]',
];

const paletteFor = (title: string): string => {
  let hash = 0;
  for (let i = 0; i < title.length; i += 1) {
    hash = (hash * 31 + title.charCodeAt(i)) | 0;
  }
  return palettes[Math.abs(hash) % palettes.length];
};

export function AuctionVisual({ title, imageUrl, children, className = '' }: Props) {
  return (
    <div className={`relative overflow-hidden bg-zinc-950 ${className}`}>
      {imageUrl ? (
        // Seeded demo assets live in /public; user-created lots may omit image_url.
        // eslint-disable-next-line @next/next/no-img-element
        <img src={imageUrl} alt={title} className="h-full w-full object-contain" />
      ) : (
        <div
          className={`flex h-full w-full items-center justify-center bg-gradient-to-br ${paletteFor(title)}`}
        >
          <span className="select-none text-5xl font-black tracking-tight text-amber-200/25">
            {title.slice(0, 1)}
          </span>
        </div>
      )}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/25 via-transparent to-transparent"
      />
      {children}
    </div>
  );
}
