'use client';

import { useRouter } from 'next/navigation';
import { FormEvent, useState } from 'react';
import { createAuction } from '@/lib/auctions';
import { useUser } from '@/lib/auth';

const toLocalIso = (offsetMinutes: number): string => {
  const d = new Date(Date.now() + offsetMinutes * 60 * 1000);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

export default function NewAuctionPage() {
  const router = useRouter();
  const { auth } = useUser();
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [startingPrice, setStartingPrice] = useState(1000);
  const [bidIncrement, setBidIncrement] = useState(100);
  const [startsAt, setStartsAt] = useState(() => toLocalIso(-1));
  const [endsAt, setEndsAt] = useState(() => toLocalIso(60));
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  if (auth.state === 'unauthenticated') {
    router.replace('/login');
    return null;
  }
  if (auth.state === 'loading') {
    return <main className="p-8 text-sm">読み込み中...</main>;
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      const created = await createAuction({
        title,
        description,
        starting_price: startingPrice,
        bid_increment: bidIncrement,
        starts_at: new Date(startsAt).toISOString(),
        ends_at: new Date(endsAt).toISOString(),
      });
      router.push(`/auctions/${created.id}`);
    } catch (err: unknown) {
      const message =
        err && typeof err === 'object' && 'response' in err
          ? extractMessage(err)
          : err instanceof Error
            ? err.message
            : 'failed';
      setError(message);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <main className="mx-auto mt-12 flex w-full max-w-2xl flex-col gap-4 p-6">
      <h1 className="text-2xl font-semibold">出品する</h1>

      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Field label="タイトル">
          <input
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            required
            maxLength={120}
            className={inputClasses}
          />
        </Field>

        <Field label="説明">
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            required
            rows={3}
            className={inputClasses}
          />
        </Field>

        <div className="grid grid-cols-2 gap-4">
          <Field label="開始価格 (円)">
            <input
              type="number"
              min={1}
              value={startingPrice}
              onChange={(e) => setStartingPrice(Number(e.target.value))}
              className={inputClasses}
            />
          </Field>
          <Field label="入札単位 (円)">
            <input
              type="number"
              min={1}
              value={bidIncrement}
              onChange={(e) => setBidIncrement(Number(e.target.value))}
              className={inputClasses}
            />
          </Field>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <Field label="開始日時">
            <input
              type="datetime-local"
              value={startsAt}
              onChange={(e) => setStartsAt(e.target.value)}
              className={inputClasses}
            />
          </Field>
          <Field label="終了日時">
            <input
              type="datetime-local"
              value={endsAt}
              onChange={(e) => setEndsAt(e.target.value)}
              className={inputClasses}
            />
          </Field>
        </div>

        {error && (
          <p role="alert" className="text-sm text-red-600 dark:text-red-400">
            {error}
          </p>
        )}

        <button
          type="submit"
          disabled={submitting}
          className="self-start rounded bg-zinc-900 px-4 py-2 text-base font-medium text-white disabled:opacity-50 dark:bg-zinc-100 dark:text-zinc-900"
        >
          {submitting ? '送信中…' : '出品する'}
        </button>
      </form>
    </main>
  );
}

const inputClasses =
  'w-full rounded border border-zinc-300 bg-white px-3 py-2 text-base dark:border-zinc-700 dark:bg-zinc-950';

const Field = ({ label, children }: { label: string; children: React.ReactNode }) => (
  <label className="flex flex-col gap-1 text-sm">
    {label}
    {children}
  </label>
);

const extractMessage = (err: { response?: unknown }): string => {
  const response = err.response;
  if (
    response &&
    typeof response === 'object' &&
    'data' in response &&
    response.data &&
    typeof response.data === 'object' &&
    'message' in response.data &&
    typeof response.data.message === 'string'
  ) {
    return response.data.message;
  }
  return 'failed to create auction';
};
