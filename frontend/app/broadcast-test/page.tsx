'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { getEcho } from '@/lib/echo';

type PingPayload = {
  message: string;
  emitted_at: string;
};

type ReceivedPing = PingPayload & {
  receivedAt: string;
};

export default function BroadcastTestPage() {
  const [pings, setPings] = useState<ReceivedPing[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [connected, setConnected] = useState(false);

  useEffect(() => {
    const echo = getEcho();
    const channel = echo.channel('public.ping');

    const onConnected = () => setConnected(true);
    const onDisconnected = () => setConnected(false);

    echo.connector.pusher.connection.bind('connected', onConnected);
    echo.connector.pusher.connection.bind('disconnected', onDisconnected);

    channel.listen('.ping', (payload: PingPayload) => {
      setPings((prev) => [
        { ...payload, receivedAt: new Date().toISOString() },
        ...prev,
      ]);
    });

    return () => {
      echo.connector.pusher.connection.unbind('connected', onConnected);
      echo.connector.pusher.connection.unbind('disconnected', onDisconnected);
      echo.leave('public.ping');
    };
  }, []);

  const triggerPing = async () => {
    setError(null);
    try {
      await api.post('/api/broadcast-test');
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'broadcast failed');
    }
  };

  return (
    <main className="mx-auto mt-16 flex w-full max-w-2xl flex-col gap-6 p-8">
      <h1 className="text-2xl font-semibold">Broadcast 動作確認</h1>

      <section className="flex items-center gap-3">
        <span
          className={`inline-block h-2 w-2 rounded-full ${
            connected ? 'bg-emerald-500' : 'bg-zinc-400'
          }`}
        />
        <span className="text-sm">
          Reverb: {connected ? 'connected' : 'disconnected'}
        </span>
      </section>

      <button
        type="button"
        onClick={triggerPing}
        className="self-start rounded bg-zinc-900 px-4 py-2 text-base font-medium text-white dark:bg-zinc-100 dark:text-zinc-900"
      >
        ping を broadcast
      </button>

      {error && (
        <p className="text-sm text-red-600 dark:text-red-400" role="alert">
          {error}
        </p>
      )}

      <ol className="flex flex-col gap-2">
        {pings.length === 0 && (
          <li className="text-sm text-zinc-500">受信した ping はまだありません</li>
        )}
        {pings.map((ping, index) => (
          <li
            key={`${ping.emitted_at}-${index}`}
            className="rounded border border-zinc-300 p-3 text-sm dark:border-zinc-700"
          >
            <div className="font-mono">{ping.message}</div>
            <div className="text-xs text-zinc-500">emitted: {ping.emitted_at}</div>
            <div className="text-xs text-zinc-500">received: {ping.receivedAt}</div>
          </li>
        ))}
      </ol>
    </main>
  );
}
