'use client';

import Echo from 'laravel-echo';
import Pusher, { type ChannelAuthorizationCallback } from 'pusher-js';
import { api } from './api';

let echoInstance: Echo<'reverb'> | null = null;

export const getEcho = (): Echo<'reverb'> => {
  if (echoInstance) {
    return echoInstance;
  }

  if (typeof window === 'undefined') {
    throw new Error('Echo can only be initialised in the browser');
  }

  const w = window as Window & { Pusher?: typeof Pusher };
  w.Pusher = Pusher;

  const key = process.env.NEXT_PUBLIC_REVERB_APP_KEY ?? '';
  const host = process.env.NEXT_PUBLIC_REVERB_HOST ?? 'localhost';
  const port = Number.parseInt(process.env.NEXT_PUBLIC_REVERB_PORT ?? '8080', 10);
  const scheme = process.env.NEXT_PUBLIC_REVERB_SCHEME ?? 'http';

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    // Private / Presence チャンネルの認可は Sanctum SPA のセッション Cookie を
    // 使うため、既存の axios インスタンス (withCredentials + XSRF ヘッダ付き) で
    // /broadcasting/auth を叩く独自 authorizer に差し替える。
    // pusher-js 既定の XHR は Cookie を送らないため、そのままでは認可できない。
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: ChannelAuthorizationCallback) => {
        api
          .post('/broadcasting/auth', {
            socket_id: socketId,
            channel_name: channel.name,
          })
          .then((response) => callback(null, response.data))
          .catch((error: Error) => callback(error, null));
      },
    }),
  });

  return echoInstance;
};
